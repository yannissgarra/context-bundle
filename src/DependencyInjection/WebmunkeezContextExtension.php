<?php

/*
 * (c) Yannis Sgarra <hello@yannissgarra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Webmunkeez\ContextBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * @author Yannis Sgarra <hello@yannissgarra.com>
 */
final class WebmunkeezContextExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('webmunkeez_context.secret', $config['secret']);
        $container->setParameter('webmunkeez_context.ttl', $config['ttl']);
        $container->setParameter('webmunkeez_context.refresh_after', $config['refresh_after']);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../../config'), $container->getParameter('kernel.environment'));
        $loader->load('context.php');
        $loader->load('event_listener.php');
        $loader->load('token.php');
    }
}
