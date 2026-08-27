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
use Webmunkeez\ContextBundle\Exception\ContextClassNotFoundException;
use Webmunkeez\ContextBundle\Test\Context\CustomTtlContext;
use Webmunkeez\ContextBundle\Test\Context\FooBarContext;
use Webmunkeez\ContextBundle\Test\Context\UnparseableRefreshAfterContext;
use Webmunkeez\ContextBundle\Token\ContextToken;
use Webmunkeez\ContextBundle\Token\ContextTokenEncoderInterface;

/**
 * @author Yannis Sgarra <hello@yannissgarra.com>
 */
final class ContextRequestListenerTest extends TestCase
{
    public function testOnKernelRequestWithMatchingCookieShouldSucceed(): void
    {
        $request = new Request(cookies: ['profile_context' => 'profile-token']);

        $tokenEncoder = $this->createMock(ContextTokenEncoderInterface::class);
        $tokenEncoder->method('decode')->with('profile-token')->willReturn(
            new ContextToken(['hash' => 'cached'], new \DateTimeImmutable(), FooBarContext::class),
        );

        $listener = new ContextRequestListener($tokenEncoder);
        $listener->onKernelRequest(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $this->assertSame(['hash' => 'cached'], $request->attributes->get('context.profile'));
        $this->assertSame(FooBarContext::class, $request->attributes->get('context.profile.class'));
    }

    public function testOnKernelRequestWithMultipleMatchingCookiesShouldSucceed(): void
    {
        $request = new Request(cookies: [
            'profile_context' => 'profile-token',
            'foo_bar_context' => 'foo-bar-token',
        ]);

        $tokenEncoder = $this->createMock(ContextTokenEncoderInterface::class);
        $tokenEncoder->method('decode')->willReturnMap([
            ['profile-token', new ContextToken(['hash' => 'profile-hash'], new \DateTimeImmutable(), FooBarContext::class)],
            ['foo-bar-token', new ContextToken(['hash' => 'foo-bar-hash'], new \DateTimeImmutable(), FooBarContext::class)],
        ]);

        $listener = new ContextRequestListener($tokenEncoder);
        $listener->onKernelRequest(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $this->assertSame(['hash' => 'profile-hash'], $request->attributes->get('context.profile'));
        $this->assertSame(['hash' => 'foo-bar-hash'], $request->attributes->get('context.foo-bar'));
    }

    public function testOnKernelRequestWithoutMatchingCookieShouldFail(): void
    {
        $request = new Request(cookies: ['unrelated_cookie' => 'value']);

        $tokenEncoder = $this->createMock(ContextTokenEncoderInterface::class);
        $tokenEncoder->expects($this->never())->method('decode');

        $listener = new ContextRequestListener($tokenEncoder);
        $listener->onKernelRequest(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $this->assertSame([], $request->attributes->all());
    }

    public function testOnKernelRequestWithInvalidTokenShouldFail(): void
    {
        $request = new Request(cookies: ['profile_context' => 'tampered-token']);

        $tokenEncoder = $this->createMock(ContextTokenEncoderInterface::class);
        $tokenEncoder->method('decode')->with('tampered-token')->willReturn(null);

        $listener = new ContextRequestListener($tokenEncoder);
        $listener->onKernelRequest(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $this->assertSame([], $request->attributes->all());
    }

    public function testOnKernelRequestOnSubRequestShouldFail(): void
    {
        $request = new Request(cookies: ['profile_context' => 'profile-token']);

        $tokenEncoder = $this->createMock(ContextTokenEncoderInterface::class);
        $tokenEncoder->expects($this->never())->method('decode');

        $listener = new ContextRequestListener($tokenEncoder);
        $listener->onKernelRequest(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::SUB_REQUEST));

        $this->assertSame([], $request->attributes->all());
    }

    public function testOnKernelRequestWithStaleTokenShouldSetRefreshFlag(): void
    {
        $request = new Request(cookies: ['profile_context' => 'profile-token']);

        $tokenEncoder = $this->createMock(ContextTokenEncoderInterface::class);
        $tokenEncoder->method('decode')->with('profile-token')->willReturn(
            new ContextToken(['hash' => 'cached'], new \DateTimeImmutable('-2 days'), FooBarContext::class),
        );

        $listener = new ContextRequestListener($tokenEncoder);
        $listener->onKernelRequest(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $this->assertTrue($request->attributes->get('context.profile.refresh'));
    }

    public function testOnKernelRequestWithFreshTokenShouldNotSetRefreshFlag(): void
    {
        $request = new Request(cookies: ['profile_context' => 'profile-token']);

        $tokenEncoder = $this->createMock(ContextTokenEncoderInterface::class);
        $tokenEncoder->method('decode')->with('profile-token')->willReturn(
            new ContextToken(['hash' => 'cached'], new \DateTimeImmutable('-1 hour'), FooBarContext::class),
        );

        $listener = new ContextRequestListener($tokenEncoder);
        $listener->onKernelRequest(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $this->assertFalse($request->attributes->has('context.profile.refresh'));
    }

    public function testOnKernelRequestUsesContextClassRefreshAfterShouldSucceed(): void
    {
        $request = new Request(cookies: ['custom_ttl_context' => 'custom-token']);

        // CustomTtlContext::getRefreshAfter() is '2 hours': 1 hour old is not stale yet
        $tokenEncoder = $this->createMock(ContextTokenEncoderInterface::class);
        $tokenEncoder->method('decode')->with('custom-token')->willReturn(
            new ContextToken(['hash' => 'cached'], new \DateTimeImmutable('-1 hour'), CustomTtlContext::class),
        );

        $listener = new ContextRequestListener($tokenEncoder);
        $listener->onKernelRequest(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $this->assertFalse($request->attributes->has('context.custom-ttl.refresh'));
    }

    public function testOnKernelRequestWithUnknownContextClassShouldSetDeleteFlag(): void
    {
        $request = new Request(cookies: ['profile_context' => 'orphaned-token']);

        $tokenEncoder = $this->createMock(ContextTokenEncoderInterface::class);
        $tokenEncoder->method('decode')->with('orphaned-token')->willThrowException(
            new ContextClassNotFoundException('Removed\\ProfileContext'),
        );

        $listener = new ContextRequestListener($tokenEncoder);
        $listener->onKernelRequest(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $this->assertTrue($request->attributes->get('context.profile.delete'));
        $this->assertFalse($request->attributes->has('context.profile'));
    }

    public function testOnKernelRequestWithUnparseableRefreshAfterShouldNotThrowOrSetRefreshFlag(): void
    {
        $request = new Request(cookies: ['unparseable_refresh_after_context' => 'token']);

        $tokenEncoder = $this->createMock(ContextTokenEncoderInterface::class);
        $tokenEncoder->method('decode')->with('token')->willReturn(
            new ContextToken(['hash' => 'cached'], new \DateTimeImmutable('-2 days'), UnparseableRefreshAfterContext::class),
        );

        $listener = new ContextRequestListener($tokenEncoder);
        $listener->onKernelRequest(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $this->assertSame(['hash' => 'cached'], $request->attributes->get('context.unparseable-refresh-after'));
        $this->assertFalse($request->attributes->has('context.unparseable-refresh-after.refresh'));
    }
}
