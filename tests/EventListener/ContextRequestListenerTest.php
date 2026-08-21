<?php

/*
 * (c) Yannis Sgarra <hello@yannissgarra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Webmunkeez\ContextBundle\Test\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Webmunkeez\ContextBundle\EventListener\ContextRequestListener;

/**
 * @author Yannis Sgarra <hello@yannissgarra.com>
 */
final class ContextRequestListenerTest extends TestCase
{
    public function testOnKernelRequestWithMatchingCookieShouldSucceed(): void
    {
        $request = new Request(cookies: ['profile_context' => '{"hash":"cached"}']);

        $listener = new ContextRequestListener();
        $listener->onKernelRequest(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $this->assertSame('{"hash":"cached"}', $request->attributes->get('context.profile'));
    }

    public function testOnKernelRequestWithMultipleMatchingCookiesShouldSucceed(): void
    {
        $request = new Request(cookies: [
            'profile_context' => '{"hash":"profile-hash"}',
            'foo_bar_context' => '{"hash":"foo-bar-hash"}',
        ]);

        $listener = new ContextRequestListener();
        $listener->onKernelRequest(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $this->assertSame('{"hash":"profile-hash"}', $request->attributes->get('context.profile'));
        $this->assertSame('{"hash":"foo-bar-hash"}', $request->attributes->get('context.foo-bar'));
    }

    public function testOnKernelRequestWithoutMatchingCookieShouldFail(): void
    {
        $request = new Request(cookies: ['unrelated_cookie' => 'value']);

        $listener = new ContextRequestListener();
        $listener->onKernelRequest(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $this->assertSame([], $request->attributes->all());
    }
}
