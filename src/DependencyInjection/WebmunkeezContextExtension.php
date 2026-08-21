<?php

/*
 * (c) Yannis Sgarra <hello@yannissgarra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Webmunkeez\ContextBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * @author Yannis Sgarra <hello@yannissgarra.com>
 */
final class WebmunkeezContextExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
    }
}
