<?php

/*
 * (c) Yannis Sgarra <hello@yannissgarra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Webmunkeez\ContextBundle\Jwt\JwtTokenEncoder;
use Webmunkeez\ContextBundle\Token\TokenEncoderInterface;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(JwtTokenEncoder::class)
            ->args([param('webmunkeez_context.secret'), param('webmunkeez_context.ttl')])

        ->alias(TokenEncoderInterface::class, JwtTokenEncoder::class);
};
