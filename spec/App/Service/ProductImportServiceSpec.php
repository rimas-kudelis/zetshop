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
        $productRepository->findOneByCode('code')->willReturn(null);
        $query->getOneOrNullResult()->willReturn(null);

        // A product should be created and added to the repository
        $productFactory->createNew()->shouldBeCalled()->willReturn($product);
        $product->setCode('code')->shouldBeCalled();
        $product->setSlug('slug')->shouldBeCalled();
        $product->setName('name')->shouldBeCalled();
        $product->setDescription('description')->shouldBeCalled();
        $productRepository->add($product)->shouldBeCalled();

        // These calls will be made too
        $product->getCode()->willReturn('code');
        $product->getName()->willReturn('name');

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
        $channel1->getCode()->willReturn('channel1');
        $channel2->getCode()->willReturn('channel2');
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
            false
        );
    }
}
