<?php

namespace Swarrot\SwarrotBundle\Tests\DependencyInjection;

use Swarrot\SwarrotBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Yaml\Parser;
use PHPUnit\Framework\TestCase;

class ConfigurationTest extends TestCase
{
    public function test_it_is_initializable()
    {
        $this->assertInstanceOf(
            'Swarrot\SwarrotBundle\DependencyInjection\Configuration',
            new Configuration(false)
        );
    }

    /**
     * @group legacy
     */
    public function test_with_old_default_configuration()
    {
        $parser = new Parser();
        $config = $parser->parse(file_get_contents(__DIR__.'/../fixtures/old_default_configuration.yml'));

        $configuration = new Configuration(true);
        $processor = new Processor();

        $processedConfiguration = $processor->processConfiguration($configuration, [$config['swarrot']]);
        $expectedDefaultConfiguration = require_once __DIR__.'/../fixtures/old_default_configuration.php';

        $this->assertSame($expectedDefaultConfiguration, $processedConfiguration);
    }

    public function test_with_default_configuration()
    {
        $parser = new Parser();
        $config = $parser->parse(file_get_contents(__DIR__.'/../fixtures/default_configuration.yml'));

        $configuration = new Configuration(true);
        $processor = new Processor();

        $processedConfiguration = $processor->processConfiguration($configuration, [$config['swarrot']]);
        $expectedDefaultConfiguration = require_once __DIR__.'/../fixtures/default_configuration.php';

        $this->assertSame($expectedDefaultConfiguration, $processedConfiguration);
    }
}
