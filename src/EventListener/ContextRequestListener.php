<?php

/*
 * (c) Yannis Sgarra <hello@yannissgarra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Webmunkeez\ContextBundle\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Webmunkeez\ContextBundle\Token\TokenEncoderInterface;

/**
 * @author Yannis Sgarra <hello@yannissgarra.com>
 */
final class ContextRequestListener
{
    public function __construct(
        private readonly TokenEncoderInterface $tokenEncoder,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (false === $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        foreach ($request->cookies->all() as $name => $value) {
            if (str_ends_with($name, '_context')) {
                $reference = substr($name, 0, -strlen('_context'));

                $payload = $this->tokenEncoder->decode($value);

                if (null !== $payload) {
                    $request->attributes->set('context.'.str_replace('_', '-', $reference), $payload);
                }
            }
        }
    }
}
