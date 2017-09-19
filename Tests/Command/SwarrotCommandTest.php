<?php

namespace Swarrot\SwarrotBundle\Tests\Command;

use Swarrot\Broker\Message;
use Swarrot\Processor\ConfigurableInterface;
use Swarrot\Processor\ProcessorInterface;
use Swarrot\SwarrotBundle\Command\SwarrotCommand;
use Swarrot\SwarrotBundle\Processor\ProcessorConfiguratorEnableAware;
use Swarrot\SwarrotBundle\Processor\ProcessorConfiguratorExtrasAware;
use Swarrot\SwarrotBundle\Processor\ProcessorConfiguratorInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SwarrotCommandTest extends \PHPUnit_Framework_TestCase
{
    public function test_it_add_options_from_processor_configurators()
    {
        $processor = $this->prophesize('Swarrot\Processor\ProcessorInterface');

        $processorConfigurator1 = $this->prophesize('Swarrot\SwarrotBundle\Processor\ProcessorConfiguratorInterface');
        $processorConfigurator1->getCommandOptions()->willReturn([['option1'], ['option2']]);
        $processorConfigurator2 = $this->prophesize('Swarrot\SwarrotBundle\Processor\ProcessorConfiguratorInterface');
        $processorConfigurator2->getCommandOptions()->willReturn([['option3']]);
        $processorConfigurators = array($processorConfigurator1->reveal(), $processorConfigurator2->reveal());

        $command = new SwarrotCommand('foobar', 'foobar', $processor->reveal(), $processorConfigurators, array());

        $this->assertTrue($command->getDefinition()->hasOption('option1'));
        $this->assertTrue($command->getDefinition()->hasOption('option2'));
        $this->assertTrue($command->getDefinition()->hasOption('option3'));
    }

    /**
     * @dataProvider it_merges_arguments_from_config_and_command_line_dataprovider
     */
    public function test_it_merges_arguments_from_config_and_command_line($commandOptions, $extras, $expectedResolvedOptions)
    {
        $container = $this->prophesize('Symfony\Component\DependencyInjection\ContainerInterface');
        $factory = $this->prophesize('Swarrot\SwarrotBundle\Broker\FactoryInterface');
        $messageProvider = $this->prophesize('Swarrot\Broker\MessageProvider\MessageProviderInterface');

        $container->get('swarrot.factory.default')->willReturn($factory->reveal());
        $messageProvider->getQueueName()->willReturn('queue name');
        $factory->getMessageProvider('queue name', 'connection name')->willReturn($messageProvider->reveal());
        $messageProvider->get()->willReturn(new Message());

        $processor = new TestFinalProcessor();
        $processorConfigurator = new TestProcessorConfigurator();
        $processorConfigurator->setExtras($extras);

        $command = new SwarrotCommand('foobar', 'foobar', $processor, [$processorConfigurator], $extras);
        $command->setContainer($container->reveal());

        $commandTester = new CommandTester($command);
        $commandTester->execute(['queue' => 'queue name', 'connection' => 'connection name'] + $commandOptions);

        $this->assertArraySubset($expectedResolvedOptions, $processor->processCallOptions);
    }

    public function it_merges_arguments_from_config_and_command_line_dataprovider()
    {
        return [
            'No option from the command line and no extras' => [
                [], [], ['option1' => 'default extra value 1', 'option2' => 'default extra value 2'],
            ],
            'No option from the command line and extras' => [
                [], ['option1' => 'extra value 1', 'option2' => 'extra value 2'], ['option1' => 'extra value 1', 'option2' => 'extra value 2'],
            ],
            'Option from the command line and extras' => [
                ['--option1' => 'value 1', '--option2' => 'value 2'], ['option1' => 'extra value 1', 'option2' => 'extra value 2'], ['option1' => 'value 1', 'option2' => 'value 2'],
            ],
        ];
    }
}

class TestProcessorConfigurator implements ProcessorConfiguratorInterface
{
    use ProcessorConfiguratorEnableAware, ProcessorConfiguratorExtrasAware;

    public function getCommandOptions()
    {
        return [
            ['option1', 'o1', InputOption::VALUE_REQUIRED, 'descr', $this->getExtra('option1', 'default extra value 1')],
            ['option2', 'o2', InputOption::VALUE_REQUIRED, 'descr', $this->getExtra('option2', 'default extra value 2')],
        ];
    }

    public function resolveOptions(InputInterface $input)
    {
        return [
            'option1' => $input->getOption('option1'),
            'option2' => $input->getOption('option2'),
        ] + $this->getExtras();
    }

    public function getProcessorArguments(array $options)
    {
        return ['Swarrot\SwarrotBundle\Tests\Command\TestProcessor'];
    }
}

class TestProcessor implements ConfigurableInterface
{
    /**@var ProcessorInterface */
    protected $processor;

    public function __construct(ProcessorInterface $processor)
    {
        $this->processor = $processor;
    }

    public function process(Message $message, array $options)
    {
        return $this->processor->process($message, $options);
    }

    public function setDefaultOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['option1' => 'default value 1', 'option2' => 'default value 2']);
    }
}

class TestFinalProcessor implements ProcessorInterface
{
    public $processCallOptions = [];

    public function process(Message $message, array $options)
    {
        $this->processCallOptions = $options;
        // return false to stop the consumer.
        return false;
    }
}