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

/**
 * @author Yannis Sgarra <hello@yannissgarra.com>
 */
final class ContextRequestListener
{
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        foreach ($request->cookies->all() as $name => $value) {
            if (str_ends_with($name, '_context')) {
                $reference = substr($name, 0, -strlen('_context'));

                $request->attributes->set('context.'.str_replace('_', '-', $reference), $value);
            }
        }
    }
}
