<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Html2Text\Html2Text;
use RuntimeException;
use Sylius\Component\Attribute\Factory\AttributeFactoryInterface;
use Sylius\Component\Attribute\Model\AttributeInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\OutputStyle;
use Symfony\Component\Console\Style\SymfonyStyle;

class ImportProductsCommand extends Command
{
    // Argument and option names and default values (where appropriate)
    const ARGUMENT_JSON_FILE = 'json-file';
    const ARGUMENT_CHANNEL = 'channel';
    const OPTION_UPDATE_EXISTING = 'update-existing-products';
    const OPTION_UPDATE_EXISTING_SHORT = 'u';
    const OPTION_CREATE_CATEGORIES = 'create-category-taxons';
    const OPTION_CREATE_CATEGORIES_SHORT = 'c';
    const OPTION_CREATE_PRODUCERS = 'create-producer-taxons';
    const OPTION_CREATE_PRODUCERS_SHORT = 'p';
    const OPTION_SKIP_RECORDS = 'skip-records';
    const OPTION_SKIP_RECORDS_SHORT = 's';
    const OPTION_MAX_RECORDS = 'max-records';
    const OPTION_MAX_RECORDS_SHORT = 'm';

    const PRODUCT_CREATED = 1;
    const PRODUCT_UPDATED = 2;
    const PRODUCT_SKIPPED_DUPLICATE = 3;

    /** @var AttributeFactoryInterface */
    protected $attributeFactory;

    /** @var RepositoryInterface */
    protected $attributeRepository;

    /** @var FactoryInterface */
    protected $attributeValueFactory;

    /** @var ChannelRepositoryInterface */
    protected $channelRepository;

    /** @var FactoryInterface */
    protected $channelPricingFactory;

    /** @var RepositoryInterface */
    protected $channelPricingRepository;

    /** @var EntityManagerInterface */
    protected $em;

    /** @var string */
    protected $localeCode;

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
        ChannelRepositoryInterface $channelRepository,
        FactoryInterface $channelPricingFactory,
        RepositoryInterface $channelPricingRepository,
        EntityManagerInterface $em,
        FactoryInterface $productFactory,
        ProductRepositoryInterface $productRepository,
        FactoryInterface $productTaxonFactory,
        ProductTaxonRepositoryInterface $productTaxonRepository,
        FactoryInterface $productVariantFactory,
        ProductVariantRepositoryInterface $productVariantRepository,
        FactoryInterface $taxonFactory,
        RepositoryInterface $taxonRepository,
        string $localeCode
    ) {
        parent::__construct();

        $this->attributeFactory = $attributeFactory;
        $this->attributeRepository = $attributeRepository;
        $this->attributeValueFactory = $attributeValueFactory;
        $this->channelRepository = $channelRepository;
        $this->channelPricingFactory = $channelPricingFactory;
        $this->channelPricingRepository = $channelPricingRepository;
        $this->em = $em;
        $this->productFactory = $productFactory;
        $this->productRepository = $productRepository;
        $this->productTaxonFactory = $productTaxonFactory;
        $this->productTaxonRepository = $productTaxonRepository;
        $this->productVariantFactory = $productVariantFactory;
        $this->productVariantRepository = $productVariantRepository;
        $this->taxonFactory = $taxonFactory;
        $this->taxonRepository = $taxonRepository;
        $this->localeCode = $localeCode;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('app:import:product')
            ->setDescription('Import products from a JSON file.')
            ->setHelp('Imports products from a supplied JSON file.')
            ->addArgument(
                self::ARGUMENT_JSON_FILE,
                InputArgument::REQUIRED,
                'Path to the JSON file with products.'
            )
            ->addArgument(
                self::ARGUMENT_CHANNEL,
                InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
                'Channel(s) to enable all imported products in (use channel code(s)).'
            )
            ->addOption(
                self::OPTION_UPDATE_EXISTING,
                self::OPTION_UPDATE_EXISTING_SHORT,
                InputOption::VALUE_NONE,
                'Update existing products with same EAN codes.'
            )
            ->addOption(
                self::OPTION_CREATE_CATEGORIES,
                self::OPTION_CREATE_CATEGORIES_SHORT,
                InputOption::VALUE_NONE,
                'Create and apply taxons for `category_id` field values (the field will be ignored if this option not specified).'
            )
            ->addOption(
                self::OPTION_CREATE_PRODUCERS,
                self::OPTION_CREATE_PRODUCERS_SHORT,
                InputOption::VALUE_NONE,
                'Create and apply taxons for `producer_id` field values (the field will be ignored if this option not specified).'
            )
            ->addOption(
                self::OPTION_SKIP_RECORDS,
                self::OPTION_SKIP_RECORDS_SHORT,
                InputOption::VALUE_REQUIRED,
                sprintf('Skip first n records.'),
                0
            )
            ->addOption(
                self::OPTION_MAX_RECORDS,
                self::OPTION_MAX_RECORDS_SHORT,
                InputOption::VALUE_REQUIRED,
                sprintf('Only process first n records (after skipping).')
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $io = new SymfonyStyle($input, $output);

        // Read and parse the file
        $filePath = $input->getArgument(self::ARGUMENT_JSON_FILE);
        $io->writeln(sprintf('Reading file <info>%s</info>.', $filePath));
        $io->newLine();
        $data = $this->parseFile($filePath);
        $totalRecords = count($data);
        $io->success(sprintf('The file has been read and parsed successfully. %d records have been found.', $totalRecords));

        // Collect channel references, if the argument has been supplied
        $channelCodes = $input->getArgument(self::ARGUMENT_CHANNEL);
        if (empty($channelCodes)) {
            $io->warning('No channels have been specified. The products imported will not be visible in the store.');
            $channels = [];
        } else {
            $channels = $this->getChannels($channelCodes);
        }

        // Begin import
        $update = $input->getOption(self::OPTION_UPDATE_EXISTING);
        $skipRecords = max(0, $input->getOption(self::OPTION_SKIP_RECORDS));
        $maxRecords = $input->getOption(self::OPTION_MAX_RECORDS);
        $createCategories = $input->getOption(self::OPTION_CREATE_CATEGORIES);
        $createProducers = $input->getOption(self::OPTION_CREATE_PRODUCERS);
        if ($skipRecords >= $totalRecords) {
            $io->note('The specified number of records to skip is larger than the total number of records. Existing.');
            return;
        }

        $recordsToProcess = min($totalRecords - $skipRecords, ($maxRecords ?? PHP_INT_MAX));
        $io->progressStart($recordsToProcess);

        $skippedRecords = $invalidRecords = $createdProducts = $updatedProducts = 0;

        for ($i = $skipRecords; $i < $skipRecords + $recordsToProcess; $i++) {
            $record = $data[$i];
            if (($i % 100 === 0) && ($i !== 0)) {
                // Clean up the entity manager every 100 rows to avoid the import slowing down drastically.
                // It still slows down, but considerably less than without this precaution.
                $this->cleanUpEntityManager();

                // Re-fetch channels: previously fetched ones are now detached and will cause EM to throw if used.
                if (!empty($channelCodes)) {
                    $channels = $this->getChannels($channelCodes);
                }
            }

            $io->progressAdvance();

            if (empty($record['ean']) || empty($record['slug']) || empty($record['title'])) {
                $invalidRecords++;
                continue;
            }

            $taxonNames = [];
            if ($createCategories && !empty($record['category_id'])) {
                $taxonNames[] = 'cat_'.$record['category_id'];
            }
            if ($createProducers && !empty($record['producer_id'])) {
                $taxonNames[] = 'prod_'.$record['producer_id'];
            }

            $result = $this->importProduct(
                (string)$record['ean'],
                (string)$record['slug'],
                (string)$record['title'],
                (string)($record['description'] ?? ''),
                (int)($record['quantity'] ?? 0),
                (int)(($record['price'] ?? 0) * 100),
                $channels,
                $taxonNames,
                $update
            );

            switch ($result) {
                case self::PRODUCT_CREATED:
                    $createdProducts++;
                    break;
                case self::PRODUCT_UPDATED:
                    $updatedProducts++;
                    break;
                case self::PRODUCT_SKIPPED_DUPLICATE:
                    $skippedRecords++;
                    break;
            }
        }

        $io->progressFinish();

        // Report outcome of the operation
        $messageLevel = $invalidRecords > 0 ? 'warning' : 'success';
        $messages = [
            'Done!',
            sprintf('%d products have been created.', $createdProducts),
        ];
        if ($updatedProducts > 0) {
            $messages[] = sprintf('%d products have been updated.', $updatedProducts);
        }
        if ($skippedRecords) {
            $messages[] = sprintf('%d records have been deemed as duplicates and skipped.', $skippedRecords);
        }
        if ($invalidRecords > 0) {
            $messages[] = sprintf('%d records have been skipped due to lack of mandatory fields.', $invalidRecords);
        }

        $io->$messageLevel($messages);
    }

    /**
     * Read and parse a JSON file. Throws if result is empty.
     */
    protected function parseFile(string $filePath): array
    {
        $fileContents = file_get_contents($filePath);
        $data = json_decode($fileContents, true);

        if (empty($data)) {
            throw new RuntimeException('The file is empty or does not contain valid non-empty JSON.');
        }

        return $data;
    }

    /**
     * Gets an array of channel codes, returns an array of channels. Throws if any of the channels does not exist.
     */
    protected function getChannels(array $channelCodes): array
    {
        $channels = [];
        foreach ($channelCodes as $channelCode) {
            $channel = $this->channelRepository->findOneByCode($channelCode);
            if ($channel === null) {
                throw new RuntimeException(sprintf('Channel `%s` could not be found!', $channelCode));
            }

            $channels[] = $channel;
        }

        return $channels;
    }

    /**
     * Creates or updates (optionally, if $update = true) a product and its related entities.
     */
    protected function importProduct(
        string $code,
        string $slug,
        string $name,
        string $description = '',
        int $quantity = 0,
        int $price = 0,
        array $channels = [],
        array $taxonNames = [],
        bool $update = false
    ): int {
        $product = $this->productRepository->findOneByCode($code);
        // Ensure we won't try to use a slug that is already used by a different product.
        $productBySlug = $this->getProductBySlug($slug);
        if ($productBySlug !== null && $productBySlug != $product) {
            $suffix = 0;
            do {
                $suffix++;
                $newSlug = sprintf('%s-%d', $slug, $suffix);
                $productBySlug = $this->getProductBySlug($newSlug);
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
     * Fetches or creates a product attribute by its code.
     */
    protected function getOrCreateAttribute(
        OutputStyle $io,
        string $code
    ): AttributeInterface {
        $attribute = $this->attributeRepository->findOneByCode($code);

        if ($attribute === null) {
            $io->note(sprintf('Attribute `%s` not found, creating.', $code));
            $attribute = $this->attributeFactory->createTyped('text');
            $attribute->setName(mb_convert_case($code, MB_CASE_TITLE));
            $attribute->setCode($code);
            $this->attributeRepository->add($attribute);
        }

        return $attribute;
    }

    /**
     * Find a product by its slug.
     * The ProductRepository class has a findOneByChannelAndSlug method, but that also requires a channel
     * to be passed, so doesn't exactly suit here.
     */
    protected function getProductBySlug(string $slug): ?ProductInterface
    {
        $product = $this->productRepository->createQueryBuilder('o')
            ->addSelect('translation')
            ->innerJoin('o.translations', 'translation', 'WITH', 'translation.locale = :locale')
            ->andWhere('translation.slug = :slug')
            ->andWhere('o.enabled = true')
            ->setParameter('locale', $this->localeCode)
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

    /**
     * Clean up the entity manager.
     */
    protected function cleanUpEntityManager(): void
    {
        $this->em->flush();
        $this->em->clear();
    }
}
