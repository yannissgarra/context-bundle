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
use Webmunkeez\ContextBundle\Exception\ContextClassNotFoundException;
use Webmunkeez\ContextBundle\Token\ContextTokenEncoderInterface;

/**
 * @author Yannis Sgarra <hello@yannissgarra.com>
 */
final class ContextRequestListener
{
    public function __construct(
        private readonly ContextTokenEncoderInterface $tokenEncoder,
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
                $reference = str_replace('_', '-', substr($name, 0, -strlen('_context')));

                try {
                    $contextToken = $this->tokenEncoder->decode($value);
                } catch (ContextClassNotFoundException) {
                    $request->attributes->set('context.'.$reference.'.delete', true);

                    continue;
                }

                if (null !== $contextToken) {
                    $request->attributes->set('context.'.$reference, $contextToken->getPayload());
                    $request->attributes->set('context.'.$reference.'.class', $contextToken->getContextClass());

                    if ($contextToken->getIssuedAt() <= new \DateTimeImmutable('-'.$contextToken->getContextClass()::getRefreshAfter())) {
                        $request->attributes->set('context.'.$reference.'.refresh', true);
                    }
                }
            }
        }
    }
}
