<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Html2Text\Html2Text;
use RuntimeException;
use Sylius\Component\Attribute\Factory\AttributeFactoryInterface;
use Sylius\Component\Attribute\Model\AttributeInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Core\Repository\ProductTaxonRepositoryInterface;
use Sylius\Component\Product\Factory\ProductFactoryInterface;
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
    const ARGUMENT_JSON_FILE = 'json-file';
    const ARGUMENT_CHANNEL = 'channel';
    const OPTION_UPDATE_EXISTING = 'update-existing-products';
    const OPTION_UPDATE_EXISTING_SHORT = 'u';
    const OPTION_CREATE_CATEGORIES = 'create-category-taxons';
    const OPTION_CREATE_CATEGORIES_SHORT = 'c';
    const OPTION_SET_PRODUCER = 'set-producer';
    const OPTION_SET_PRODUCER_SHORT = 'p';
    const OPTION_SET_PRODUCER_DEFAULT_VALUE = 'producer';

    /** @var AttributeFactoryInterface */
    protected $attributeFactory;

    /** @var RepositoryInterface */
    protected $attributeRepository;

    /** @var FactoryInterface */
    protected $attributeValueFactory;

    /** @var ChannelRepositoryInterface */
    protected $channelRepository;

    /** @var string */
    protected $localeCode;

    /** @var ProductFactoryInterface */
    protected $productFactory;

    /** @var ProductRepositoryInterface */
    protected $productRepository;

    /** @var FactoryInterface */
    protected $productTaxonFactory;

    /** @var ProductTaxonRepositoryInterface */
    protected $productTaxonRepository;

    /** @var FactoryInterface */
    protected $taxonFactory;

    /** @var RepositoryInterface */
    protected $taxonRepository;

    public function __construct(
        AttributeFactoryInterface $attributeFactory,
        RepositoryInterface $attributeRepository,
        FactoryInterface $attributeValueFactory,
        ChannelRepositoryInterface $channelRepository,
        ProductFactoryInterface $productFactory,
        ProductRepositoryInterface $productRepository,
        FactoryInterface $productTaxonFactory,
        ProductTaxonRepositoryInterface $productTaxonRepository,
        FactoryInterface $taxonFactory,
        RepositoryInterface $taxonRepository,
        string $localeCode
    ) {
        parent::__construct();

        $this->attributeFactory = $attributeFactory;
        $this->attributeRepository = $attributeRepository;
        $this->attributeValueFactory = $attributeValueFactory;
        $this->channelRepository = $channelRepository;
        $this->productFactory = $productFactory;
        $this->productRepository = $productRepository;
        $this->productTaxonFactory = $productTaxonFactory;
        $this->productTaxonRepository = $productTaxonRepository;
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
                'Update existing products with same codes.'
            )
            ->addOption(
                self::OPTION_CREATE_CATEGORIES,
                self::OPTION_CREATE_CATEGORIES_SHORT,
                InputOption::VALUE_NONE,
                'Create and apply taxons matching category_id field values.'
            )
            ->addOption(
                self::OPTION_SET_PRODUCER,
                self::OPTION_SET_PRODUCER_SHORT,
                InputOption::VALUE_OPTIONAL,
                sprintf('Set producer attribute to producer_id field value. Attribute name may be specified as a value for this option, and defaults to `%s`.', self::OPTION_SET_PRODUCER_DEFAULT_VALUE),
                false
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
        $data = $this->parseFile($io, $filePath);
        $totalProducts = count($data);
        $io->success(sprintf('File has been read and parsed successfully. %d entries have been found.', $totalProducts));

        // Collect channel references, if the argument has been supplied
        $channels = $this->getChannels($io, $input->getArgument(self::ARGUMENT_CHANNEL));
        if (empty($channels)) {
            $io->warning('No channels have been specified. The products imported will not be visible in the store.');
        }

        // Check if we'll be setting the producer attribute, and prepare for that if we will
        if ($producerAttributeCode = $input->getOption(self::OPTION_SET_PRODUCER) ?? self::OPTION_SET_PRODUCER_DEFAULT_VALUE) {
            $producerAttribute = $this->getOrCreateAttribute($io, $producerAttributeCode);
        } else {
            $producerAttribute = null;
        }

        // Begin import
        $update = $input->getOption(self::OPTION_UPDATE_EXISTING);
        $io->writeln('Commencing import.');
        $io->progressStart($totalProducts);

        foreach ($data as $i => $entry) {
            $io->progressAdvance();

            if (empty($entry['title']) || empty($entry['ean']) || empty($entry['slug'])) {
                $io->note(sprintf('Skipping product #%d: not all required fiels are defined.', $i));
                continue;
            }

            $product = $this->productRepository->findOneByCode($entry['ean']);
            if ($product) {
                if (!$update) {
                    $io->note(sprintf('A duplicate product already exists for product #%d. Skipping (use `%s` or `%s` to update such products instead).', $i, self::OPTION_UPDATE_EXISTING, self::OPTION_UPDATE_EXISTING_SHORT));
                    continue;
                }

                $io->note(sprintf('A duplicate product already exists for product #%d. It will be updated.', $i));
            } else {
                $product = $this->productFactory->createNew();
            }

            // Set basic properties
            $product->setName($entry['title']);
            $product->setCode($entry['ean']);
            $product->setSlug($entry['slug']);
            $product->setDescription(Html2Text::convert($entry['description'], false));

            // Add channels if specified
            foreach ($channels as $channel) {
                $product->addChannel($channel);
            }

            // Add the producer attribute if specified
            if ($producerAttribute !== null && $entry['producer_id']) {
                // It's possible that we are updating an existing product.
                // If that is the case, we should update the existing attribute value if it exists instead of creating a new one.
                $producerAttributeValue = $product->getAttributeByCodeAndLocale($producerAttribute->getCode(), $this->localeCode);
                if ($producerAttributeValue === null) {
                    $producerAttributeValue = $this->attributeValueFactory->createNew();
                }
                $producerAttributeValue->setAttribute($producerAttribute);
                $producerAttributeValue->setValue((string)$entry['producer_id']);
                $producerAttributeValue->setLocaleCode($this->localeCode);

                $product->addAttribute($producerAttributeValue);
            }

            // Add taxons if specified
            if ($input->getOption(self::OPTION_CREATE_CATEGORIES) && $entry['category_id']) {
                $taxon = $this->getOrCreateTaxon($io, (string)$entry['category_id']);

                // If there is no main taxon set, set it to this one
                if ($product->getMainTaxon() === null) {
                    $product->setMainTaxon($taxon);
                }

                // Check if a similar productTaxon already exists before creating it
                $productTaxon = $this->productTaxonRepository->findOneByProductCodeAndTaxonCode($product->getCode(), $taxon->getCode());
                if ($productTaxon === null) {
                    $productTaxon = $this->productTaxonFactory->createNew();
                    $productTaxon->setTaxon($taxon);
                    $productTaxon->setProduct($product);

                    $product->addProductTaxon($productTaxon);
                }
            }

            $this->productRepository->add($product);
        }

        $io->progressFinish();
    }

    /**
     * Read and parse a JSON file. Throws if result is empty.
     */
    protected function parseFile(OutputStyle $io, string $filePath): array
    {
        $io->writeln(sprintf(
            'Reading file <info>%s</info>.',
            $filePath
        ));
        $io->newLine();

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
    protected function getChannels(OutputStyle $io, array $channelCodes): array
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
     * Fetches or creates a product attribute.
     */
    protected function getOrCreateAttribute(OutputStyle $io, string $code): AttributeInterface
    {
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
     * Fetchers or creates a taxon.
     */
    protected function getOrCreateTaxon(OutputStyle $io, string $code): TaxonInterface
    {
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
}