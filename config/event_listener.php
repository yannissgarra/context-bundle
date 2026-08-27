<?php

/*
 * (c) Yannis Sgarra <hello@yannissgarra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Webmunkeez\ContextBundle\EventListener\ContextRequestListener;
use Webmunkeez\ContextBundle\EventListener\ContextResponseListener;
use Webmunkeez\ContextBundle\Token\TokenEncoderInterface;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(ContextRequestListener::class)
            ->args([service(TokenEncoderInterface::class), param('webmunkeez_context.refresh_after')])
            ->tag('kernel.event_listener', ['event' => 'kernel.request'])

        ->set(ContextResponseListener::class)
            ->args([service(TokenEncoderInterface::class), param('webmunkeez_context.ttl')])
            ->tag('kernel.event_listener', ['event' => 'kernel.response']);
};
