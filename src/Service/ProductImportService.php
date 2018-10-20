<?php

declare(strict_types=1);

namespace App\Service;

use Html2Text\Html2Text;
use Sylius\Component\Attribute\Factory\AttributeFactoryInterface;
use Sylius\Component\Attribute\Model\AttributeInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Core\Repository\ProductTaxonRepositoryInterface;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Product\Model\ProductVariantInterface;
use Sylius\Component\Product\Repository\ProductVariantRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;

class ProductImportService
{
    // Possible return values for importProduct()
    const PRODUCT_CREATED = 1;
    const PRODUCT_UPDATED = 2;
    const PRODUCT_SKIPPED_DUPLICATE = 3;

    /** @var AttributeFactoryInterface */
    protected $attributeFactory;

    /** @var RepositoryInterface */
    protected $attributeRepository;

    /** @var FactoryInterface */
    protected $attributeValueFactory;

    /** @var FactoryInterface */
    protected $channelPricingFactory;

    /** @var RepositoryInterface */
    protected $channelPricingRepository;

    /** @var FactoryInterface */
    protected $productFactory;

    /** @var ProductRepositoryInterface */
    protected $productRepository;

    /** @var FactoryInterface */
    protected $productTaxonFactory;

    /** @var ProductTaxonRepositoryInterface */
    protected $productTaxonRepository;

    /** @var FactoryInterface */
    protected $productVariantFactory;

    /** @var ProductVariantRepositoryInterface */
    protected $productVariantRepository;

    /** @var FactoryInterface */
    protected $taxonFactory;

    /** @var RepositoryInterface */
    protected $taxonRepository;

    public function __construct(
        AttributeFactoryInterface $attributeFactory,
        RepositoryInterface $attributeRepository,
        FactoryInterface $attributeValueFactory,
        FactoryInterface $channelPricingFactory,
        RepositoryInterface $channelPricingRepository,
        FactoryInterface $productFactory,
        ProductRepositoryInterface $productRepository,
        FactoryInterface $productTaxonFactory,
        ProductTaxonRepositoryInterface $productTaxonRepository,
        FactoryInterface $productVariantFactory,
        ProductVariantRepositoryInterface $productVariantRepository,
        FactoryInterface $taxonFactory,
        RepositoryInterface $taxonRepository
    ) {
        $this->attributeFactory = $attributeFactory;
        $this->attributeRepository = $attributeRepository;
        $this->attributeValueFactory = $attributeValueFactory;
        $this->channelPricingFactory = $channelPricingFactory;
        $this->channelPricingRepository = $channelPricingRepository;
        $this->productFactory = $productFactory;
        $this->productRepository = $productRepository;
        $this->productTaxonFactory = $productTaxonFactory;
        $this->productTaxonRepository = $productTaxonRepository;
        $this->productVariantFactory = $productVariantFactory;
        $this->productVariantRepository = $productVariantRepository;
        $this->taxonFactory = $taxonFactory;
        $this->taxonRepository = $taxonRepository;
    }

    /**
     * Creates or updates (optionally, if $update = true) a product and its related entities.
     */
    public function importProduct(
        string $code,
        string $slug,
        string $name,
        string $localeCode,
        string $description = '',
        int $quantity = 0,
        int $price = 0,
        array $channels = [],
        array $taxonNames = [],
        bool $update = false
    ): int {
        $product = $this->productRepository->findOneByCode($code);
        // Ensure we won't try to use a slug that is already used by a different product.
        $productBySlug = $this->getProductBySlug($slug, $localeCode);
        if ($productBySlug !== null && $productBySlug != $product) {
            $suffix = 0;
            do {
                $suffix++;
                $newSlug = sprintf('%s-%d', $slug, $suffix);
                $productBySlug = $this->getProductBySlug($newSlug, $localeCode);
            } while ($productBySlug !== null && $productBySlug != $product);
            $slug = $newSlug;
        }

        if ($product !== null) {
            if (!$update) {
                return self::PRODUCT_SKIPPED_DUPLICATE;
            }

            $this->updateProduct($product, $name, $description);
            $result = self::PRODUCT_UPDATED;
        } else {
            $product = $this->createProduct($code, $slug, $name, $description);
            $result = self::PRODUCT_CREATED;
        }

        // Add taxons if specified
        foreach ($taxonNames as $taxonName) {
            $this->addProductTaxon($product, $taxonName, false);
        }

        // Create or update default product variant and pricing
        $variant = $this->createOrUpdateDefaultProductVariant($product, $quantity);

        // Add channels and pricings if specified
        foreach ($channels as $channel) {
            if ($price) {
                $product->addChannel($channel);

                $this->createOrUpdateProductVariantChannelPricing($variant, $channel, $price);
            }
        }

        return $result;
    }

    /**
     * Find a product by its slug.
     * The ProductRepository class has a findOneByChannelAndSlug method, but that also requires a channel
     * to be passed, so doesn't exactly suit here.
     */
    protected function getProductBySlug(string $slug, string $localeCode): ?ProductInterface
    {
        $product = $this->productRepository->createQueryBuilder('o')
            ->addSelect('translation')
            ->innerJoin('o.translations', 'translation', 'WITH', 'translation.locale = :locale')
            ->andWhere('translation.slug = :slug')
            ->andWhere('o.enabled = true')
            ->setParameter('locale', $localeCode)
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $product;
    }

    /**
     * Creates a new product using data supplied, or returns null if any of the crucial data is missing.
     */
    protected function createProduct(
        string $code,
        string $slug,
        string $name,
        string $description
    ): ProductInterface {
        $product = $this->productFactory->createNew();
        $product->setCode($code);
        $product->setSlug($slug);
        $product->setName($name);
        $product->setDescription(Html2Text::convert($description, true));

        $this->productRepository->add($product);

        return $product;
    }

    /**
     * Updates a product and saves it.
     */
    protected function updateProduct(
        ProductInterface $product,
        string $name,
        string $description
    ): void {
        if (!empty($name)) {
            $product->setName($name);
        }

        $textDescription = Html2Text::convert($description, true);
        if (!empty($textDescription)) {
            $product->setDescription($textDescription);
        }
    }

    /**
     * Add product to the given taxon by is code.
     */
    protected function addProductTaxon(
        ProductInterface $product,
        string $code,
        bool $setAsMainTaxon = false
    ): void {
        $taxon = $this->getOrCreateTaxon($code);

        // If there is no main taxon set, set it to this one
        if ($setAsMainTaxon) {
            $product->setMainTaxon($taxon);
        }

        // Check if a similar productTaxon already exists before creating it
        $productTaxon = $this->productTaxonRepository->findOneByProductCodeAndTaxonCode($product->getCode(), $code);
        if ($productTaxon === null) {
            $productTaxon = $this->productTaxonFactory->createNew();
            $productTaxon->setTaxon($taxon);
            $productTaxon->setProduct($product);

            $product->addProductTaxon($productTaxon);
        }
    }

    /**
     * Fetchers or creates a taxon by its code.
     */
    protected function getOrCreateTaxon(
        string $code
    ): TaxonInterface {
        $taxon = $this->taxonRepository->findOneByCode($code);

        if ($taxon === null) {
            $taxon = $this->taxonFactory->createNew();
            $taxon->setCode($code);
            $taxon->setName($code);
            $taxon->setSlug($code);

            $this->taxonRepository->add($taxon);
        }

        return $taxon;
    }

    /**
     * Create or update default variant for product.
     */
    protected function createOrUpdateDefaultProductVariant(
        ProductInterface $product,
        ?int $quantity
    ): ProductVariantInterface {
        $variant = $this->productVariantRepository->findOneByCodeAndProductCode($product->getCode(), $product->getCode());

        if ($variant === null) {
            $variant = $this->productVariantFactory->createNew();
            $variant->setName($product->getName());
            $variant->setCode($product->getCode());
            $variant->setProduct($product);
        }

        $variant->setOnHand($quantity ?? 0);

        $this->productVariantRepository->add($variant);

        return $variant;
    }

    /**
     * Create or update ProductVariant pricing for a channel.
     */
    protected function createOrUpdateProductVariantChannelPricing(
        ProductVariantInterface $variant,
        ChannelInterface $channel,
        int $price
    ): ChannelPricingInterface {
        // Not using the more convenient getter from variant to avoid getting empty outdated result
        // and then consequently encountering a UniqueConstraintViolationException.
        // $variant->getChannelPricingForChannel($channel);
        $pricing = $this->channelPricingRepository->findOneBy([
            'productVariant' => $variant,
            'channelCode' => $channel->getCode(),
        ]);

        if ($pricing === null) {
            $pricing = $this->channelPricingFactory->createNew();
            $pricing->setProductVariant($variant);
            $pricing->setChannelCode($channel->getCode());
        }

        $pricing->setPrice($price);

        $this->channelPricingRepository->add($pricing);

        return $pricing;
    }
}
