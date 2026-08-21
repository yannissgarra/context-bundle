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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Webmunkeez\ContextBundle\EventListener\ContextResponseListener;

/**
 * @author Yannis Sgarra <hello@yannissgarra.com>
 */
final class ContextResponseListenerTest extends TestCase
{
    public function testOnKernelResponseWithRefreshFlagShouldSucceed(): void
    {
        $request = new Request();
        $request->attributes->set('context.profile', '{"hash":"cached"}');
        $request->attributes->set('context.profile.refresh', true);

        $response = new Response();

        $listener = new ContextResponseListener();
        $listener->onKernelResponse(new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $cookie = $response->headers->getCookies()[0] ?? null;

        $this->assertNotNull($cookie);
        $this->assertSame('profile_context', $cookie->getName());
        $this->assertSame('{"hash":"cached"}', $cookie->getValue());
    }

    public function testOnKernelResponseWithMultipleRefreshFlagsShouldSucceed(): void
    {
        $request = new Request();
        $request->attributes->set('context.profile', '{"hash":"profile-hash"}');
        $request->attributes->set('context.profile.refresh', true);
        $request->attributes->set('context.foo-bar', '{"hash":"foo-bar-hash"}');
        $request->attributes->set('context.foo-bar.refresh', true);

        $response = new Response();

        $listener = new ContextResponseListener();
        $listener->onKernelResponse(new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $names = array_map(static fn ($cookie) => $cookie->getName(), $response->headers->getCookies());

        $this->assertContains('profile_context', $names);
        $this->assertContains('foo_bar_context', $names);
    }

    public function testOnKernelResponseWithoutRefreshFlagShouldFail(): void
    {
        $request = new Request();
        $request->attributes->set('context.profile', '{"hash":"cached"}');

        $response = new Response();

        $listener = new ContextResponseListener();
        $listener->onKernelResponse(new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $this->assertSame([], $response->headers->getCookies());
    }

    public function testOnKernelResponseWithRefreshFlagFalseShouldNotSetCookie(): void
    {
        $request = new Request();
        $request->attributes->set('context.profile', '{"hash":"cached"}');
        $request->attributes->set('context.profile.refresh', false);

        $response = new Response();

        $listener = new ContextResponseListener();
        $listener->onKernelResponse(new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $this->assertSame([], $response->headers->getCookies());
    }

    public function testOnKernelResponseOnSubRequestShouldFail(): void
    {
        $request = new Request();
        $request->attributes->set('context.profile', '{"hash":"cached"}');
        $request->attributes->set('context.profile.refresh', true);

        $response = new Response();

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST, $response);

        $listener = new ContextResponseListener();
        $listener->onKernelResponse($event);

        $this->assertSame([], $response->headers->getCookies());
    }
}
