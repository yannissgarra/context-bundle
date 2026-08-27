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
use Webmunkeez\ContextBundle\Test\Context\CustomTtlContext;
use Webmunkeez\ContextBundle\Test\Context\FooBarContext;
use Webmunkeez\ContextBundle\Token\ContextTokenEncoderInterface;

/**
 * @author Yannis Sgarra <hello@yannissgarra.com>
 */
final class ContextResponseListenerTest extends TestCase
{
    public function testOnKernelResponseWithRefreshFlagShouldSucceed(): void
    {
        $request = new Request();
        $request->attributes->set('context.profile', ['hash' => 'cached']);
        $request->attributes->set('context.profile.class', FooBarContext::class);
        $request->attributes->set('context.profile.refresh', true);

        $response = new Response();

        $tokenEncoder = $this->createMock(ContextTokenEncoderInterface::class);
        $tokenEncoder->method('encode')->with(['hash' => 'cached'], FooBarContext::class)->willReturn('profile-token');

        $listener = new ContextResponseListener($tokenEncoder);
        $listener->onKernelResponse(new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $cookie = $response->headers->getCookies()[0] ?? null;

        $this->assertNotNull($cookie);
        $this->assertSame('profile_context', $cookie->getName());
        $this->assertSame('profile-token', $cookie->getValue());
        $this->assertFalse($cookie->isSecure());
    }

    public function testOnKernelResponseOverHttpsShouldSetSecureCookie(): void
    {
        $request = new Request(server: ['HTTPS' => 'on']);
        $request->attributes->set('context.profile', ['hash' => 'cached']);
        $request->attributes->set('context.profile.class', FooBarContext::class);
        $request->attributes->set('context.profile.refresh', true);

        $response = new Response();

        $tokenEncoder = $this->createMock(ContextTokenEncoderInterface::class);
        $tokenEncoder->method('encode')->willReturn('profile-token');

        $listener = new ContextResponseListener($tokenEncoder);
        $listener->onKernelResponse(new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $cookie = $response->headers->getCookies()[0] ?? null;

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure());
    }

    public function testOnKernelResponseUsesContextClassTtlShouldSucceed(): void
    {
        $request = new Request();
        $request->attributes->set('context.custom-ttl', ['hash' => 'cached']);
        $request->attributes->set('context.custom-ttl.class', CustomTtlContext::class);
        $request->attributes->set('context.custom-ttl.refresh', true);

        $response = new Response();

        $tokenEncoder = $this->createMock(ContextTokenEncoderInterface::class);
        $tokenEncoder->method('encode')->willReturn('custom-token');

        $listener = new ContextResponseListener($tokenEncoder);
        $listener->onKernelResponse(new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $cookie = $response->headers->getCookies()[0] ?? null;
        $expectedExpiry = (new \DateTimeImmutable('+'.CustomTtlContext::getTtl()))->getTimestamp();

        $this->assertNotNull($cookie);
        $this->assertLessThanOrEqual(2, abs($cookie->getExpiresTime() - $expectedExpiry));
    }

    public function testOnKernelResponseWithMultipleRefreshFlagsShouldSucceed(): void
    {
        $request = new Request();
        $request->attributes->set('context.profile', ['hash' => 'profile-hash']);
        $request->attributes->set('context.profile.class', FooBarContext::class);
        $request->attributes->set('context.profile.refresh', true);
        $request->attributes->set('context.foo-bar', ['hash' => 'foo-bar-hash']);
        $request->attributes->set('context.foo-bar.class', FooBarContext::class);
        $request->attributes->set('context.foo-bar.refresh', true);

        $response = new Response();

        $tokenEncoder = $this->createMock(ContextTokenEncoderInterface::class);
        $tokenEncoder->method('encode')->willReturnMap([
            [['hash' => 'profile-hash'], FooBarContext::class, 'profile-token'],
            [['hash' => 'foo-bar-hash'], FooBarContext::class, 'foo-bar-token'],
        ]);

        $listener = new ContextResponseListener($tokenEncoder);
        $listener->onKernelResponse(new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $cookiesByName = [];
        foreach ($response->headers->getCookies() as $cookie) {
            $cookiesByName[$cookie->getName()] = $cookie->getValue();
        }

        $this->assertSame('profile-token', $cookiesByName['profile_context'] ?? null);
        $this->assertSame('foo-bar-token', $cookiesByName['foo_bar_context'] ?? null);
    }

    public function testOnKernelResponseWithoutRefreshFlagShouldFail(): void
    {
        $request = new Request();
        $request->attributes->set('context.profile', ['hash' => 'cached']);

        $response = new Response();

        $tokenEncoder = $this->createMock(ContextTokenEncoderInterface::class);
        $tokenEncoder->expects($this->never())->method('encode');

        $listener = new ContextResponseListener($tokenEncoder);
        $listener->onKernelResponse(new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $this->assertSame([], $response->headers->getCookies());
    }

    public function testOnKernelResponseWithRefreshFlagFalseShouldNotSetCookie(): void
    {
        $request = new Request();
        $request->attributes->set('context.profile', ['hash' => 'cached']);
        $request->attributes->set('context.profile.class', FooBarContext::class);
        $request->attributes->set('context.profile.refresh', false);

        $response = new Response();

        $tokenEncoder = $this->createMock(ContextTokenEncoderInterface::class);
        $tokenEncoder->expects($this->never())->method('encode');

        $listener = new ContextResponseListener($tokenEncoder);
        $listener->onKernelResponse(new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $this->assertSame([], $response->headers->getCookies());
    }

    public function testOnKernelResponseOnSubRequestShouldFail(): void
    {
        $request = new Request();
        $request->attributes->set('context.profile', ['hash' => 'cached']);
        $request->attributes->set('context.profile.class', FooBarContext::class);
        $request->attributes->set('context.profile.refresh', true);

        $response = new Response();

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST, $response);

        $tokenEncoder = $this->createMock(ContextTokenEncoderInterface::class);
        $tokenEncoder->expects($this->never())->method('encode');

        $listener = new ContextResponseListener($tokenEncoder);
        $listener->onKernelResponse($event);

        $this->assertSame([], $response->headers->getCookies());
    }

    public function testOnKernelResponseWithMissingClassAttributeShouldFail(): void
    {
        $request = new Request();
        $request->attributes->set('context.profile', ['hash' => 'cached']);
        $request->attributes->set('context.profile.refresh', true);

        $response = new Response();

        $tokenEncoder = $this->createMock(ContextTokenEncoderInterface::class);
        $tokenEncoder->expects($this->never())->method('encode');

        $listener = new ContextResponseListener($tokenEncoder);
        $listener->onKernelResponse(new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $this->assertSame([], $response->headers->getCookies());
    }

    public function testOnKernelResponseWithNonContextClassAttributeShouldFail(): void
    {
        $request = new Request();
        $request->attributes->set('context.profile', ['hash' => 'cached']);
        $request->attributes->set('context.profile.class', self::class);
        $request->attributes->set('context.profile.refresh', true);

        $response = new Response();

        $tokenEncoder = $this->createMock(ContextTokenEncoderInterface::class);
        $tokenEncoder->expects($this->never())->method('encode');

        $listener = new ContextResponseListener($tokenEncoder);
        $listener->onKernelResponse(new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $this->assertSame([], $response->headers->getCookies());
    }

    public function testOnKernelResponseWithDeleteFlagShouldClearCookie(): void
    {
        $request = new Request();
        $request->attributes->set('context.profile.delete', true);

        $response = new Response();

        $tokenEncoder = $this->createMock(ContextTokenEncoderInterface::class);
        $tokenEncoder->expects($this->never())->method('encode');

        $listener = new ContextResponseListener($tokenEncoder);
        $listener->onKernelResponse(new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $cookie = $response->headers->getCookies()[0] ?? null;

        $this->assertNotNull($cookie);
        $this->assertSame('profile_context', $cookie->getName());
        $this->assertTrue($cookie->isCleared());
    }

    public function testOnKernelResponseWithDeleteFlagFalseShouldNotClearCookie(): void
    {
        $request = new Request();
        $request->attributes->set('context.profile.delete', false);

        $response = new Response();

        $tokenEncoder = $this->createMock(ContextTokenEncoderInterface::class);

        $listener = new ContextResponseListener($tokenEncoder);
        $listener->onKernelResponse(new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response));

        $this->assertSame([], $response->headers->getCookies());
    }

    public function testOnKernelResponseWithDeleteFlagOnSubRequestShouldFail(): void
    {
        $request = new Request();
        $request->attributes->set('context.profile.delete', true);

        $response = new Response();

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST, $response);

        $tokenEncoder = $this->createMock(ContextTokenEncoderInterface::class);

        $listener = new ContextResponseListener($tokenEncoder);
        $listener->onKernelResponse($event);

        $this->assertSame([], $response->headers->getCookies());
    }
}
