<?php

/*
 * (c) Yannis Sgarra <hello@yannissgarra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Webmunkeez\ContextBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @author Yannis Sgarra <hello@yannissgarra.com>
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('webmunkeez_context');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('secret')
                    ->cannotBeEmpty()
                    ->defaultValue('%kernel.secret%')
                ->end()
                ->scalarNode('ttl')
                    ->cannotBeEmpty()
                    ->defaultValue('1 year')
                    ->validate()
                        ->ifTrue(fn (string $ttl): bool => false === strtotime('+'.$ttl))
                            ->thenInvalid('Invalid ttl %s')
                    ->end()
                ->end()
                ->scalarNode('refresh_after')
                    ->cannotBeEmpty()
                    ->defaultValue('1 day')
                    ->validate()
                        ->ifTrue(fn (string $refreshAfter): bool => false === strtotime('+'.$refreshAfter))
                            ->thenInvalid('Invalid refresh_after %s')
                    ->end()
                ->end()
            ->end()
            ->validate()
                ->ifTrue(fn (array $config): bool => strtotime('+'.$config['refresh_after']) >= strtotime('+'.$config['ttl']))
                    ->thenInvalid('refresh_after must be shorter than ttl')
            ->end();

        return $treeBuilder;
    }
}
