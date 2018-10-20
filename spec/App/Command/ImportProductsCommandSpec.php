<?php

namespace spec\App\Command;

use App\Command\ImportProductsCommand;
use App\Service\ProductImportService;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class ImportProductsCommandSpec extends ObjectBehavior
{
    /** @var string path to test files */
    protected $resourcePath;

    function __construct()
    {
        $this->resourcePath = dirname(__FILE__, 4).'/resources';
    }

    function let(
        ChannelRepositoryInterface $repository,
        EntityManagerInterface $em,
        ProductImportService $service,
        InputInterface $input,
        OutputInterface $output,
        OutputFormatterInterface $formatter
    ) {
        $this->beConstructedWith($repository, $em, $service, 'lt');

        $input->bind(Argument::cetera())->willReturn();
        $input->isInteractive()->willReturn(false);
        $input->validate()->willReturn();
        $input->hasArgument('command')->willReturn(false);

        $output->getFormatter()->willReturn($formatter);
        $output->getVerbosity()->willReturn();
        $output->isDecorated()->willReturn();
        $output->writeln(Argument::cetera())->willReturn();
        $output->write(Argument::cetera())->willReturn();
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(ImportProductsCommand::class);
    }

    function it_imports_valid_different_products_with_channels_and_categories_and_producers(
        InputInterface $input,
        OutputInterface $output,
        ProductImportService $service,
        ChannelRepositoryInterface $repository,
        ChannelInterface $channel1,
        ChannelInterface $channel2
    ) {
        $input->getArgument(ImportProductsCommand::ARGUMENT_JSON_FILE)->willReturn($this->resourcePath.'/complete.json');
        $input->getArgument(ImportProductsCommand::ARGUMENT_CHANNEL)->willReturn(['channel1', 'channel2']);
        $input->getOption(ImportProductsCommand::OPTION_CREATE_CATEGORIES)->willReturn(true);
        $input->getOption(ImportProductsCommand::OPTION_CREATE_PRODUCERS)->willReturn(true);
        $input->getOption(ImportProductsCommand::OPTION_UPDATE_EXISTING)->willReturn(false);
        $input->getOption(ImportProductsCommand::OPTION_SKIP_RECORDS)->willReturn(0);
        $input->getOption(ImportProductsCommand::OPTION_MAX_RECORDS)->willReturn(null);

        $repository->findOneByCode('channel1')->willReturn($channel1);
        $repository->findOneByCode('channel2')->willReturn($channel2);

        $service->importProduct(
            'ean',
            'slug',
            'title',
            'lt',
            'description',
            111,
            1100,
            [$channel1, $channel2],
            ['cat_11111', 'prod_1111'],
            false
        )->shouldBeCalled()->willReturn(ProductImportService::PRODUCT_CREATED);

        $this->run($input, $output);
    }

    function it_passes_empty_channel_list_if_channels_not_specified(
        InputInterface $input,
        OutputInterface $output,
        ProductImportService $service,
        ChannelRepositoryInterface $repository,
        ChannelInterface $channel1,
        ChannelInterface $channel2
    ) {
        $input->getArgument(ImportProductsCommand::ARGUMENT_JSON_FILE)->willReturn($this->resourcePath.'/complete.json');
        $input->getArgument(ImportProductsCommand::ARGUMENT_CHANNEL)->willReturn(null);
        $input->getOption(ImportProductsCommand::OPTION_CREATE_CATEGORIES)->willReturn(true);
        $input->getOption(ImportProductsCommand::OPTION_CREATE_PRODUCERS)->willReturn(true);
        $input->getOption(ImportProductsCommand::OPTION_UPDATE_EXISTING)->willReturn(false);
        $input->getOption(ImportProductsCommand::OPTION_SKIP_RECORDS)->willReturn(0);
        $input->getOption(ImportProductsCommand::OPTION_MAX_RECORDS)->willReturn(null);

        $service->importProduct(
            'ean',
            'slug',
            'title',
            'lt',
            'description',
            111,
            1100,
            [],
            ['cat_11111', 'prod_1111'],
            false
        )->shouldBeCalled()->willReturn(ProductImportService::PRODUCT_CREATED);

        $this->run($input, $output);
    }

    function it_does_not_pass_category_taxon_name_if_create_categories_option_not_specified(
        InputInterface $input,
        OutputInterface $output,
        ProductImportService $service,
        ChannelRepositoryInterface $repository,
        ChannelInterface $channel1,
        ChannelInterface $channel2
    ) {
        $input->getArgument(ImportProductsCommand::ARGUMENT_JSON_FILE)->willReturn($this->resourcePath.'/complete.json');
        $input->getArgument(ImportProductsCommand::ARGUMENT_CHANNEL)->willReturn(['channel1', 'channel2']);
        $input->getOption(ImportProductsCommand::OPTION_CREATE_CATEGORIES)->willReturn(false);
        $input->getOption(ImportProductsCommand::OPTION_CREATE_PRODUCERS)->willReturn(true);
        $input->getOption(ImportProductsCommand::OPTION_UPDATE_EXISTING)->willReturn(false);
        $input->getOption(ImportProductsCommand::OPTION_SKIP_RECORDS)->willReturn(0);
        $input->getOption(ImportProductsCommand::OPTION_MAX_RECORDS)->willReturn(null);

        $repository->findOneByCode('channel1')->willReturn($channel1);
        $repository->findOneByCode('channel2')->willReturn($channel2);

        $service->importProduct(
            'ean',
            'slug',
            'title',
            'lt',
            'description',
            111,
            1100,
            [$channel1, $channel2],
            ['prod_1111'],
            false
        )->shouldBeCalled()->willReturn(ProductImportService::PRODUCT_CREATED);

        $this->run($input, $output);
    }

    function it_does_not_pass_producer_taxon_name_if_create_producers_option_not_specified(
        InputInterface $input,
        OutputInterface $output,
        ProductImportService $service,
        ChannelRepositoryInterface $repository,
        ChannelInterface $channel1,
        ChannelInterface $channel2
    ) {
        $input->getArgument(ImportProductsCommand::ARGUMENT_JSON_FILE)->willReturn($this->resourcePath.'/complete.json');
        $input->getArgument(ImportProductsCommand::ARGUMENT_CHANNEL)->willReturn(['channel1', 'channel2']);
        $input->getOption(ImportProductsCommand::OPTION_UPDATE_EXISTING)->willReturn(false);
        $input->getOption(ImportProductsCommand::OPTION_SKIP_RECORDS)->willReturn(0);
        $input->getOption(ImportProductsCommand::OPTION_MAX_RECORDS)->willReturn(null);
        $input->getOption(ImportProductsCommand::OPTION_CREATE_CATEGORIES)->willReturn(true);
        $input->getOption(ImportProductsCommand::OPTION_CREATE_PRODUCERS)->willReturn(false);

        $repository->findOneByCode('channel1')->willReturn($channel1);
        $repository->findOneByCode('channel2')->willReturn($channel2);

        $service->importProduct(
            'ean',
            'slug',
            'title',
            'lt',
            'description',
            111,
            1100,
            [$channel1, $channel2],
            ['cat_11111'],
            false
        )->shouldBeCalled()->willReturn(ProductImportService::PRODUCT_CREATED);

        $this->run($input, $output);
    }

    function it_passes_update_true_if_update_option_is_specified(
        InputInterface $input,
        OutputInterface $output,
        ProductImportService $service,
        ChannelRepositoryInterface $repository,
        ChannelInterface $channel1,
        ChannelInterface $channel2
    ) {
        $input->getArgument(ImportProductsCommand::ARGUMENT_JSON_FILE)->willReturn($this->resourcePath.'/complete.json');
        $input->getArgument(ImportProductsCommand::ARGUMENT_CHANNEL)->willReturn(['channel1', 'channel2']);
        $input->getOption(ImportProductsCommand::OPTION_CREATE_CATEGORIES)->willReturn(true);
        $input->getOption(ImportProductsCommand::OPTION_CREATE_PRODUCERS)->willReturn(true);
        $input->getOption(ImportProductsCommand::OPTION_UPDATE_EXISTING)->willReturn(true);
        $input->getOption(ImportProductsCommand::OPTION_SKIP_RECORDS)->willReturn(0);
        $input->getOption(ImportProductsCommand::OPTION_MAX_RECORDS)->willReturn(null);

        $repository->findOneByCode('channel1')->willReturn($channel1);
        $repository->findOneByCode('channel2')->willReturn($channel2);

        $service->importProduct(
            'ean',
            'slug',
            'title',
            'lt',
            'description',
            111,
            1100,
            [$channel1, $channel2],
            ['cat_11111', 'prod_1111'],
            true
        )->shouldBeCalled()->willReturn(ProductImportService::PRODUCT_CREATED);

        $this->run($input, $output);
    }

    function it_imports_multiple_records(
        InputInterface $input,
        OutputInterface $output,
        ProductImportService $service,
        ChannelRepositoryInterface $repository,
        ChannelInterface $channel1,
        ChannelInterface $channel2
    ) {
        $input->getArgument(ImportProductsCommand::ARGUMENT_JSON_FILE)->willReturn($this->resourcePath.'/multiple.json');
        $input->getArgument(ImportProductsCommand::ARGUMENT_CHANNEL)->willReturn(['channel1', 'channel2']);
        $input->getOption(ImportProductsCommand::OPTION_CREATE_CATEGORIES)->willReturn(true);
        $input->getOption(ImportProductsCommand::OPTION_CREATE_PRODUCERS)->willReturn(true);
        $input->getOption(ImportProductsCommand::OPTION_UPDATE_EXISTING)->willReturn(false);
        $input->getOption(ImportProductsCommand::OPTION_SKIP_RECORDS)->willReturn(0);
        $input->getOption(ImportProductsCommand::OPTION_MAX_RECORDS)->willReturn(null);

        $repository->findOneByCode('channel1')->willReturn($channel1);
        $repository->findOneByCode('channel2')->willReturn($channel2);

        $service->importProduct(
            'ean1',
            'slug1',
            'title1',
            'lt',
            'description1',
            111,
            1100,
            [$channel1, $channel2],
            ['cat_11111', 'prod_1111'],
            false
        )->shouldBeCalled()->willReturn(ProductImportService::PRODUCT_CREATED);

        $service->importProduct(
            'ean2',
            'slug2',
            'title2',
            'lt',
            'description2',
            222,
            2200,
            [$channel1, $channel2],
            ['cat_22222', 'prod_2222'],
            false
        )->shouldBeCalled()->willReturn(ProductImportService::PRODUCT_CREATED);

        $this->run($input, $output);
    }

    function it_skips_importing_first_n_products_if_instructed_to_do_so(
        InputInterface $input,
        OutputInterface $output,
        ProductImportService $service,
        ChannelRepositoryInterface $repository,
        ChannelInterface $channel1,
        ChannelInterface $channel2
    ) {
        $input->getArgument(ImportProductsCommand::ARGUMENT_JSON_FILE)->willReturn($this->resourcePath.'/multiple.json');
        $input->getArgument(ImportProductsCommand::ARGUMENT_CHANNEL)->willReturn(['channel1', 'channel2']);
        $input->getOption(ImportProductsCommand::OPTION_CREATE_CATEGORIES)->willReturn(true);
        $input->getOption(ImportProductsCommand::OPTION_CREATE_PRODUCERS)->willReturn(true);
        $input->getOption(ImportProductsCommand::OPTION_UPDATE_EXISTING)->willReturn(false);
        $input->getOption(ImportProductsCommand::OPTION_SKIP_RECORDS)->willReturn(1);
        $input->getOption(ImportProductsCommand::OPTION_MAX_RECORDS)->willReturn(null);

        $repository->findOneByCode('channel1')->willReturn($channel1);
        $repository->findOneByCode('channel2')->willReturn($channel2);

        $service->importProduct(
            'ean2',
            'slug2',
            'title2',
            'lt',
            'description2',
            222,
            2200,
            [$channel1, $channel2],
            ['cat_22222', 'prod_2222'],
            false
        )->shouldBeCalled()->willReturn(ProductImportService::PRODUCT_CREATED);

        $this->run($input, $output);
    }

    function it_stops_after_importing_first_n_products_if_instructed_to_do_so(
        InputInterface $input,
        OutputInterface $output,
        ProductImportService $service,
        ChannelRepositoryInterface $repository,
        ChannelInterface $channel1,
        ChannelInterface $channel2
    ) {
        $input->getArgument(ImportProductsCommand::ARGUMENT_JSON_FILE)->willReturn($this->resourcePath.'/multiple.json');
        $input->getArgument(ImportProductsCommand::ARGUMENT_CHANNEL)->willReturn(['channel1', 'channel2']);
        $input->getOption(ImportProductsCommand::OPTION_CREATE_CATEGORIES)->willReturn(true);
        $input->getOption(ImportProductsCommand::OPTION_CREATE_PRODUCERS)->willReturn(true);
        $input->getOption(ImportProductsCommand::OPTION_UPDATE_EXISTING)->willReturn(false);
        $input->getOption(ImportProductsCommand::OPTION_SKIP_RECORDS)->willReturn(0);
        $input->getOption(ImportProductsCommand::OPTION_MAX_RECORDS)->willReturn(1);

        $repository->findOneByCode('channel1')->willReturn($channel1);
        $repository->findOneByCode('channel2')->willReturn($channel2);

        $service->importProduct(
            'ean1',
            'slug1',
            'title1',
            'lt',
            'description1',
            111,
            1100,
            [$channel1, $channel2],
            ['cat_11111', 'prod_1111'],
            false
        )->shouldBeCalled()->willReturn(ProductImportService::PRODUCT_CREATED);

        $this->run($input, $output);
    }

    function it_skips_incomplete_products_from_import(
        InputInterface $input,
        OutputInterface $output,
        ProductImportService $service,
        ChannelRepositoryInterface $repository,
        ChannelInterface $channel1,
        ChannelInterface $channel2
    ) {
        $input->getArgument(ImportProductsCommand::ARGUMENT_JSON_FILE)->willReturn($this->resourcePath.'/incomplete.json');
        $input->getArgument(ImportProductsCommand::ARGUMENT_CHANNEL)->willReturn(['channel1', 'channel2']);
        $input->getOption(ImportProductsCommand::OPTION_CREATE_CATEGORIES)->willReturn(true);
        $input->getOption(ImportProductsCommand::OPTION_CREATE_PRODUCERS)->willReturn(true);
        $input->getOption(ImportProductsCommand::OPTION_UPDATE_EXISTING)->willReturn(false);
        $input->getOption(ImportProductsCommand::OPTION_SKIP_RECORDS)->willReturn(0);
        $input->getOption(ImportProductsCommand::OPTION_MAX_RECORDS)->willReturn(null);

        $repository->findOneByCode('channel1')->willReturn($channel1);
        $repository->findOneByCode('channel2')->willReturn($channel2);

        // Only the last product is valid in incomplete.json.
        $service->importProduct(
            'ean4',
            'slug4',
            'title4',
            'lt',
            'description4',
            444,
            4400,
            [$channel1, $channel2],
            ['cat_44444', 'prod_4444'],
            false
        )->shouldBeCalled()->willReturn(ProductImportService::PRODUCT_CREATED);

        $this->run($input, $output);
    }
}
