<?php

/*
 * (c) Yannis Sgarra <hello@yannissgarra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Webmunkeez\ContextBundle\EventListener;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Webmunkeez\ContextBundle\Token\TokenEncoderInterface;

/**
 * @author Yannis Sgarra <hello@yannissgarra.com>
 */
final class ContextResponseListener
{
    public function __construct(
        private readonly TokenEncoderInterface $tokenEncoder,
        private readonly string $ttl,
    ) {
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (false === $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        foreach ($request->attributes->all() as $key => $value) {
            if (
                true === $value
                && str_starts_with($key, 'context.')
                && str_ends_with($key, '.refresh')
            ) {
                $reference = substr($key, strlen('context.'), -strlen('.refresh'));

                $context = $request->attributes->get('context.'.$reference);

                if (is_array($context)) {
                    $response->headers->setCookie(
                        Cookie::create(str_replace('-', '_', $reference).'_context')
                            ->withValue($this->tokenEncoder->encode($context))
                            ->withExpires(new \DateTimeImmutable('+'.$this->ttl))
                            ->withHttpOnly(true)
                            ->withSecure($request->isSecure())
                            ->withSameSite(Cookie::SAMESITE_LAX),
                    );
                }
            }
        }
    }
}
