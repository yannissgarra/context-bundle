<?php

/*
 * (c) Yannis Sgarra <hello@yannissgarra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Webmunkeez\ContextBundle\Test\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Webmunkeez\ContextBundle\DependencyInjection\Configuration;

/**
 * @author Yannis Sgarra <hello@yannissgarra.com>
 */
final class ConfigurationTest extends TestCase
{
    public function testProcessWithoutConfigurationShouldSucceed(): void
    {
        $processedConfig = (new Processor())->processConfiguration(new Configuration(), []);

        $this->assertSame([
            'secret' => '%kernel.secret%',
            'ttl' => '1 year',
        ], $processedConfig);
    }

    public function testProcessWithCustomSecretShouldSucceed(): void
    {
        $processedConfig = (new Processor())->processConfiguration(new Configuration(), [
            'webmunkeez_context' => ['secret' => 'custom-secret'],
        ]);

        $this->assertSame('custom-secret', $processedConfig['secret']);
    }

    public function testProcessWithEmptySecretShouldThrowException(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [
            'webmunkeez_context' => ['secret' => ''],
        ]);
    }

    public function testProcessWithCustomTtlShouldSucceed(): void
    {
        $processedConfig = (new Processor())->processConfiguration(new Configuration(), [
            'webmunkeez_context' => ['ttl' => '30 days'],
        ]);

        $this->assertSame('30 days', $processedConfig['ttl']);
    }

    public function testProcessWithEmptyTtlShouldThrowException(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [
            'webmunkeez_context' => ['ttl' => ''],
        ]);
    }

    public function testProcessWithInvalidTtlShouldThrowException(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [
            'webmunkeez_context' => ['ttl' => 'not a duration'],
        ]);
    }
}
