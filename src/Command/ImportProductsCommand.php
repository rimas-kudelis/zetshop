<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ProductImportService;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
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

    /** @var ChannelRepositoryInterface */
    protected $channelRepository;

    /** @var EntityManagerInterface */
    protected $em;

    /** @var ProductImportService */
    protected $productImportService;

    /** @var string */
    protected $localeCode;

    public function __construct(
        ChannelRepositoryInterface $channelRepository,
        EntityManagerInterface $em,
        ProductImportService $productImportService,
        string $localeCode
    ) {
        parent::__construct();

        $this->channelRepository = $channelRepository;
        $this->em = $em;
        $this->productImportService = $productImportService;
        $this->localeCode = $localeCode;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('app:import:products')
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

            $result = $this->productImportService->importProduct(
                (string)$record['ean'],
                (string)$record['slug'],
                (string)$record['title'],
                $this->localeCode,
                (string)($record['description'] ?? ''),
                (int)($record['quantity'] ?? 0),
                (int)(($record['price'] ?? 0) * 100),
                $channels,
                $taxonNames,
                $update
            );

            switch ($result) {
                case ProductImportService::PRODUCT_CREATED:
                    $createdProducts++;
                    break;
                case ProductImportService::PRODUCT_UPDATED:
                    $updatedProducts++;
                    break;
                case ProductImportService::PRODUCT_SKIPPED_DUPLICATE:
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
     * Clean up the entity manager.
     */
    protected function cleanUpEntityManager(): void
    {
        $this->em->flush();
        $this->em->clear();
    }
}
