<?php

namespace spec\App\Service;

use App\Service\ProductImportService;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\QueryBuilder;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Sylius\Bundle\CoreBundle\Doctrine\ORM\ProductRepository;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTaxonInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Core\Repository\ProductTaxonRepositoryInterface;
use Sylius\Component\Product\Repository\ProductVariantRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

class ProductImportServiceSpec extends ObjectBehavior
{
    function let(
        FactoryInterface $channelPricingFactory,
        RepositoryInterface $channelPricingRepository,
        FactoryInterface $productFactory,
        ProductRepository $productRepository,
        FactoryInterface $productTaxonFactory,
        ProductTaxonRepositoryInterface $productTaxonRepository,
        FactoryInterface $productVariantFactory,
        ProductVariantRepositoryInterface $productVariantRepository,
        FactoryInterface $taxonFactory,
        RepositoryInterface $taxonRepository,
        QueryBuilder $queryBuilder,
        AbstractQuery $query
    ) {
        $this->beConstructedWith(
            $channelPricingFactory,
            $channelPricingRepository,
            $productFactory,
            $productRepository,
            $productTaxonFactory,
            $productTaxonRepository,
            $productVariantFactory,
            $productVariantRepository,
            $taxonFactory,
            $taxonRepository
        );

        // This mocks most of getProductBySlug()
        $productRepository->createQueryBuilder(Argument::cetera())->willReturn($queryBuilder);
        $queryBuilder->addSelect(Argument::cetera())->willReturn($queryBuilder);
        $queryBuilder->innerJoin(Argument::cetera())->willReturn($queryBuilder);
        $queryBuilder->andWhere(Argument::cetera())->willReturn($queryBuilder);
        $queryBuilder->setParameter(Argument::cetera())->willReturn($queryBuilder);
        $queryBuilder->getQuery()->willReturn($query);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(ProductImportService::class);
    }

    function it_creates_a_product_and_everything_else(
        FactoryInterface $channelPricingFactory,
        RepositoryInterface $channelPricingRepository,
        FactoryInterface $productFactory,
        ProductRepository $productRepository,
        FactoryInterface $productTaxonFactory,
        ProductTaxonRepositoryInterface $productTaxonRepository,
        FactoryInterface $productVariantFactory,
        ProductVariantRepositoryInterface $productVariantRepository,
        FactoryInterface $taxonFactory,
        RepositoryInterface $taxonRepository,
        AbstractQuery $query,
        ProductInterface $product,
        ChannelInterface $channel1,
        ChannelInterface $channel2,
        TaxonInterface $taxon1,
        TaxonInterface $taxon2,
        ProductTaxonInterface $productTaxon1,
        ProductTaxonInterface $productTaxon2,
        ProductVariantInterface $productVariant,
        ChannelPricingInterface $channelPricing1,
        ChannelPricingInterface $channelPricing2
    ) {
        // Search for existing product should be performed
        $productRepository->findOneByCode('code')->shouldBeCalled()->willReturn(null);
        $query->getOneOrNullResult()->shouldBeCalled()->willReturn(null);

        // A product should be created and added to the repository
        $productFactory->createNew()->shouldBeCalled()->willReturn($product);
        $product->setCode('code')->shouldBeCalled();
        $product->setSlug('slug')->shouldBeCalled();
        $product->setName('name')->shouldBeCalled();
        $product->setDescription('description')->shouldBeCalled();
        $productRepository->add($product)->shouldBeCalled();

        // Search for existing taxons should be performed
        $taxonRepository->findOneBy(['code' => 'taxon1'])->shouldBeCalled()->willReturn(null);
        $taxonRepository->findOneBy(['code' => 'taxon2'])->shouldBeCalled()->willReturn(null);

        // New taxons should be created and added to the repository
        $taxonFactory->createNew()->shouldBeCalled()->willReturn($taxon1, $taxon2);
        $taxon1->setCode('taxon1')->shouldBeCalled();
        $taxon1->setName('taxon1')->shouldBeCalled();
        $taxon1->setSlug('taxon1')->shouldBeCalled();
        $taxonRepository->add($taxon1)->shouldBeCalled();
        $taxon2->setCode('taxon2')->shouldBeCalled();
        $taxon2->setName('taxon2')->shouldBeCalled();
        $taxon2->setSlug('taxon2')->shouldBeCalled();
        $taxonRepository->add($taxon2)->shouldBeCalled();

        // Search for existing productTaxons should be performed
        $productTaxonRepository->findOneByProductCodeAndTaxonCode('code', 'taxon1')->shouldBeCalled()->willReturn(null);
        $productTaxonRepository->findOneByProductCodeAndTaxonCode('code', 'taxon2')->shouldBeCalled()->willReturn(null);

        // ProductTaxon relations should be created and added to the product
        $productTaxonFactory->createNew()->shouldBeCalled()->willReturn($productTaxon1, $productTaxon2);
        $productTaxon1->setTaxon($taxon1)->shouldBeCalled();
        $productTaxon1->setProduct($product)->shouldBeCalled();
        $product->addProductTaxon($productTaxon1)->shouldBeCalled();
        $productTaxon2->setTaxon($taxon2)->shouldBeCalled();
        $productTaxon2->setProduct($product)->shouldBeCalled();
        $product->addProductTaxon($productTaxon2)->shouldBeCalled();

        // Search for existing product variant should be performed
        $productVariantRepository->findOneByCodeAndProductCode('code', 'code')->shouldBeCalled()->willReturn(null);

        // New product variant should be created and added to the repository
        $productVariantFactory->createNew()->shouldBeCalled()->willReturn($productVariant);
        $productVariant->setName('name')->shouldBeCalled();
        $productVariant->setCode('code')->shouldBeCalled();
        $productVariant->setProduct($product)->shouldBeCalled();
        $productVariant->setOnHand(1)->shouldBeCalled();
        $productVariantRepository->add($productVariant)->shouldBeCalled();

        // Channels should be added to the product
        $product->addChannel($channel1)->shouldBeCalled();
        $product->addChannel($channel2)->shouldBeCalled();

        // Search for existing channelPricing entities should be performed
        $channelPricingRepository->findOneBy(['productVariant' => $productVariant, 'channelCode' => 'channel1'])->shouldBeCalled()->willReturn(null);
        $channelPricingRepository->findOneBy(['productVariant' => $productVariant, 'channelCode' => 'channel2'])->shouldBeCalled()->willReturn(null);

        // New channelPricing entities should be created and added to the repository
        $channelPricingFactory->createNew()->shouldBeCalled()->willReturn($channelPricing1, $channelPricing2);
        $channelPricing1->setProductVariant($productVariant)->shouldBeCalled();
        $channelPricing1->setChannelCode('channel1')->shouldBeCalled();
        $channelPricing1->setPrice(2)->shouldBeCalled();
        $channelPricingRepository->add($channelPricing1)->shouldBeCalled();
        $channelPricing2->setProductVariant($productVariant)->shouldBeCalled();
        $channelPricing2->setChannelCode('channel2')->shouldBeCalled();
        $channelPricing2->setPrice(2)->shouldBeCalled();
        $channelPricingRepository->add($channelPricing2)->shouldBeCalled();

        // These calls might be made too
        $product->getCode()->willReturn('code');
        $product->getName()->willReturn('name');
        $channel1->getCode()->willReturn('channel1');
        $channel2->getCode()->willReturn('channel2');

        $this->importProduct(
            'code',
            'slug',
            'name',
            'locale',
            'description',
            1, // quantity
            2, // price
            [$channel1, $channel2],
            ['taxon1', 'taxon2'],
            false // update
        )->shouldReturn(ProductImportService::PRODUCT_CREATED);
    }

    function it_does_nothing_if_finds_a_product_and_update_is_false(
        FactoryInterface $channelPricingFactory,
        RepositoryInterface $channelPricingRepository,
        FactoryInterface $productFactory,
        ProductRepository $productRepository,
        FactoryInterface $productTaxonFactory,
        ProductTaxonRepositoryInterface $productTaxonRepository,
        FactoryInterface $productVariantFactory,
        ProductVariantRepositoryInterface $productVariantRepository,
        FactoryInterface $taxonFactory,
        RepositoryInterface $taxonRepository,
        AbstractQuery $query,
        ProductInterface $product,
        ChannelInterface $channel1,
        ChannelInterface $channel2,
        TaxonInterface $taxon1,
        TaxonInterface $taxon2,
        ProductTaxonInterface $productTaxon1,
        ProductTaxonInterface $productTaxon2,
        ProductVariantInterface $productVariant,
        ChannelPricingInterface $channelPricing1,
        ChannelPricingInterface $channelPricing2
    ) {
        // Search for existing product should be performed
        $productRepository->findOneByCode('code')->shouldBeCalled()->willReturn($product);
        $product->setDescription(Argument::any())->shouldNotBeCalled();

        $this->importProduct(
            'code',
            'slug',
            'name',
            'locale',
            'description',
            1, // quantity
            2, // price
            [$channel1, $channel2],
            ['taxon1', 'taxon2'],
            false // update
        )->shouldReturn(ProductImportService::PRODUCT_SKIPPED_DUPLICATE);
    }

    function it_updates_a_product_if_update_is_true(
        FactoryInterface $channelPricingFactory,
        RepositoryInterface $channelPricingRepository,
        FactoryInterface $productFactory,
        ProductRepository $productRepository,
        FactoryInterface $productTaxonFactory,
        ProductTaxonRepositoryInterface $productTaxonRepository,
        FactoryInterface $productVariantFactory,
        ProductVariantRepositoryInterface $productVariantRepository,
        FactoryInterface $taxonFactory,
        RepositoryInterface $taxonRepository,
        AbstractQuery $query,
        ProductInterface $product,
        ChannelInterface $channel1,
        ChannelInterface $channel2,
        TaxonInterface $taxon1,
        TaxonInterface $taxon2,
        ProductTaxonInterface $productTaxon1,
        ProductTaxonInterface $productTaxon2,
        ProductVariantInterface $productVariant,
        ChannelPricingInterface $channelPricing1,
        ChannelPricingInterface $channelPricing2
    ) {
        // Search for existing product should be performed
        $productRepository->findOneByCode('code')->willReturn($product);

        // An existing product should be updated
        $productFactory->createNew()->shouldNotBeCalled();
        $product->setName('name')->shouldBeCalled();
        $product->setDescription('description')->shouldBeCalled();

        // Search for existing taxons should be performed
        $taxonRepository->findOneBy(['code' => 'taxon1'])->shouldBeCalled()->willReturn($taxon1);
        $taxonRepository->findOneBy(['code' => 'taxon2'])->shouldBeCalled()->willReturn($taxon2);

        // Existing taxons should not be updated
        $taxonFactory->createNew()->shouldNotBeCalled();
        $taxon1->setCode(Argument::any())->shouldNotBeCalled();
        $taxon1->setName(Argument::any())->shouldNotBeCalled();
        $taxon1->setSlug(Argument::any())->shouldNotBeCalled();
        $taxon2->setCode(Argument::any())->shouldNotBeCalled();
        $taxon2->setName(Argument::any())->shouldNotBeCalled();
        $taxon2->setSlug(Argument::any())->shouldNotBeCalled();
        $taxonRepository->add(Argument::any())->shouldNotBeCalled();

        // Search for existing productTaxons should be performed
        $productTaxonRepository->findOneByProductCodeAndTaxonCode('code', 'taxon1')->shouldBeCalled()->willReturn($productTaxon1);
        $productTaxonRepository->findOneByProductCodeAndTaxonCode('code', 'taxon2')->shouldBeCalled()->willReturn($productTaxon2);

        // Existing ProductTaxon relations should not be updated
        $productTaxonFactory->createNew()->shouldNotBeCalled();
        $productTaxon1->setTaxon(Argument::any())->shouldNotBeCalled();
        $productTaxon1->setProduct(Argument::any())->shouldNotBeCalled();
        $productTaxon2->setTaxon(Argument::any())->shouldNotBeCalled();
        $productTaxon2->setProduct(Argument::any())->shouldNotBeCalled();
        $product->addProductTaxon(Argument::any())->shouldNotBeCalled();

        // Search for existing product variant should be performed
        $productVariantRepository->findOneByCodeAndProductCode('code', 'code')->shouldBeCalled()->willReturn($productVariant);

        // Existing product variant should only have its `onHand` property updated
        $productVariantFactory->createNew()->shouldNotBeCalled();
        $productVariant->setName(Argument::any())->shouldNotBeCalled();
        $productVariant->setCode(Argument::any())->shouldNotBeCalled();
        $productVariant->setProduct(Argument::any())->shouldNotBeCalled();
        $productVariant->setOnHand(1)->shouldBeCalled();
        $productVariantRepository->add(Argument::any())->shouldNotBeCalled();

        // Channels should be added to the product
        $product->addChannel($channel1)->shouldBeCalled();
        $product->addChannel($channel2)->shouldBeCalled();

        // Search for existing channelPricing entities should be performed
        $channelPricingRepository->findOneBy(['productVariant' => $productVariant, 'channelCode' => 'channel1'])->shouldBeCalled()->willReturn($channelPricing1);
        $channelPricingRepository->findOneBy(['productVariant' => $productVariant, 'channelCode' => 'channel2'])->shouldBeCalled()->willReturn($channelPricing2);

        // Existing channelPricing entities should only have their `price` property updated
        $channelPricingFactory->createNew()->shouldNotBeCalled();
        $channelPricing1->setProductVariant(Argument::any())->shouldNotBeCalled();
        $channelPricing1->setChannelCode(Argument::any())->shouldNotBeCalled();
        $channelPricing1->setPrice(2)->shouldBeCalled();
        $channelPricing2->setProductVariant(Argument::any())->shouldNotBeCalled();
        $channelPricing2->setChannelCode(Argument::any())->shouldNotBeCalled();
        $channelPricing2->setPrice(2)->shouldBeCalled();
        $channelPricingRepository->add(Argument::any())->shouldNotBeCalled();

        // These calls might be made too
        $product->getCode()->willReturn('code');
        $product->getName()->willReturn('name');
        $channel1->getCode()->willReturn('channel1');
        $channel2->getCode()->willReturn('channel2');

        $this->importProduct(
            'code',
            'slug',
            'name',
            'locale',
            'description',
            1, // quantity
            2, // price
            [$channel1, $channel2],
            ['taxon1', 'taxon2'],
            true // update
        )->shouldReturn(ProductImportService::PRODUCT_UPDATED);
    }

    function it_creates_missing_entities_if_update_is_true(
        FactoryInterface $channelPricingFactory,
        RepositoryInterface $channelPricingRepository,
        FactoryInterface $productFactory,
        ProductRepository $productRepository,
        FactoryInterface $productTaxonFactory,
        ProductTaxonRepositoryInterface $productTaxonRepository,
        FactoryInterface $productVariantFactory,
        ProductVariantRepositoryInterface $productVariantRepository,
        FactoryInterface $taxonFactory,
        RepositoryInterface $taxonRepository,
        AbstractQuery $query,
        ProductInterface $product,
        ChannelInterface $channel1,
        ChannelInterface $channel2,
        TaxonInterface $taxon1,
        TaxonInterface $taxon2,
        ProductTaxonInterface $productTaxon1,
        ProductTaxonInterface $productTaxon2,
        ProductVariantInterface $productVariant,
        ChannelPricingInterface $channelPricing1,
        ChannelPricingInterface $channelPricing2
    ) {
        // Search for existing product should be performed
        $productRepository->findOneByCode('code')->willReturn($product);

        // An existing product should be updated
        $productFactory->createNew()->shouldNotBeCalled();
        $product->setName('name')->shouldBeCalled();
        $product->setDescription('description')->shouldBeCalled();

        // Search for existing taxons should be performed
        $taxonRepository->findOneBy(['code' => 'taxon1'])->shouldBeCalled()->willReturn(null);
        $taxonRepository->findOneBy(['code' => 'taxon2'])->shouldBeCalled()->willReturn($taxon2);

        // Existing taxons should not be updated
        $taxonFactory->createNew()->shouldBeCalledTimes(1)->willReturn($taxon1);
        $taxon1->setCode('taxon1')->shouldBeCalled();
        $taxon1->setName('taxon1')->shouldBeCalled();
        $taxon1->setSlug('taxon1')->shouldBeCalled();
        $taxonRepository->add($taxon1)->shouldBeCalled();
        $taxon2->setCode(Argument::any())->shouldNotBeCalled();
        $taxon2->setName(Argument::any())->shouldNotBeCalled();
        $taxon2->setSlug(Argument::any())->shouldNotBeCalled();
        $taxonRepository->add($taxon2)->shouldNotBeCalled();

        // Search for existing productTaxons should be performed
        $productTaxonRepository->findOneByProductCodeAndTaxonCode('code', 'taxon1')->shouldBeCalled()->willReturn(null);
        $productTaxonRepository->findOneByProductCodeAndTaxonCode('code', 'taxon2')->shouldBeCalled()->willReturn($productTaxon2);

        // Existing ProductTaxon relations should not be updated
        $productTaxonFactory->createNew()->shouldBeCalledTimes(1)->willReturn($productTaxon1);
        $productTaxon1->setTaxon($taxon1)->shouldBeCalled();
        $productTaxon1->setProduct($product)->shouldBeCalled();
        $product->addProductTaxon($productTaxon1)->shouldBeCalled();
        $productTaxon2->setTaxon(Argument::any())->shouldNotBeCalled();
        $productTaxon2->setProduct(Argument::any())->shouldNotBeCalled();
        $product->addProductTaxon($productTaxon2)->shouldNotBeCalled();

        // Search for existing product variant should be performed
        $productVariantRepository->findOneByCodeAndProductCode('code', 'code')->shouldBeCalled()->willReturn($productVariant);

        // Existing product variant should only have its `onHand` property updated
        $productVariantFactory->createNew()->shouldNotBeCalled();
        $productVariant->setName(Argument::any())->shouldNotBeCalled();
        $productVariant->setCode(Argument::any())->shouldNotBeCalled();
        $productVariant->setProduct(Argument::any())->shouldNotBeCalled();
        $productVariant->setOnHand(1)->shouldBeCalled();
        $productVariantRepository->add(Argument::any())->shouldNotBeCalled();

        // Channels should be added to the product
        $product->addChannel($channel1)->shouldBeCalled();
        $product->addChannel($channel2)->shouldBeCalled();

        // Search for existing channelPricing entities should be performed
        $channelPricingRepository->findOneBy(['productVariant' => $productVariant, 'channelCode' => 'channel1'])->shouldBeCalled()->willReturn(null);
        $channelPricingRepository->findOneBy(['productVariant' => $productVariant, 'channelCode' => 'channel2'])->shouldBeCalled()->willReturn($channelPricing2);

        // Existing channelPricing entities should only have their `price` property updated
        $channelPricingFactory->createNew()->shouldBeCalledTimes(1)->willReturn($channelPricing1);
        $channelPricing1->setProductVariant($productVariant)->shouldBeCalled();
        $channelPricing1->setChannelCode('channel1')->shouldBeCalled();
        $channelPricing1->setPrice(2)->shouldBeCalled();
        $channelPricingRepository->add($channelPricing1)->shouldBeCalled();
        $channelPricing2->setProductVariant(Argument::any())->shouldNotBeCalled();
        $channelPricing2->setChannelCode(Argument::any())->shouldNotBeCalled();
        $channelPricing2->setPrice(2)->shouldBeCalled();
        $channelPricingRepository->add($channelPricing2)->shouldNotBeCalled();

        // These calls might be made too
        $product->getCode()->willReturn('code');
        $product->getName()->willReturn('name');
        $channel1->getCode()->willReturn('channel1');
        $channel2->getCode()->willReturn('channel2');

        $this->importProduct(
            'code',
            'slug',
            'name',
            'locale',
            'description',
            1, // quantity
            2, // price
            [$channel1, $channel2],
            ['taxon1', 'taxon2'],
            true // update
        )->shouldReturn(ProductImportService::PRODUCT_UPDATED);
    }
}
