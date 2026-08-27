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
use Webmunkeez\ContextBundle\Token\TokenEncoderInterface;

/**
 * @author Yannis Sgarra <hello@yannissgarra.com>
 */
final class ContextRequestListenerTest extends TestCase
{
    public function testOnKernelRequestWithMatchingCookieShouldSucceed(): void
    {
        $request = new Request(cookies: ['profile_context' => 'profile-token']);

        $tokenEncoder = $this->createMock(TokenEncoderInterface::class);
        $tokenEncoder->method('decode')->with('profile-token')->willReturn(['hash' => 'cached']);

        $listener = new ContextRequestListener($tokenEncoder, '1 day');
        $listener->onKernelRequest(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $this->assertSame(['hash' => 'cached'], $request->attributes->get('context.profile'));
    }

    public function testOnKernelRequestWithMultipleMatchingCookiesShouldSucceed(): void
    {
        $request = new Request(cookies: [
            'profile_context' => 'profile-token',
            'foo_bar_context' => 'foo-bar-token',
        ]);

        $tokenEncoder = $this->createMock(TokenEncoderInterface::class);
        $tokenEncoder->method('decode')->willReturnMap([
            ['profile-token', ['hash' => 'profile-hash']],
            ['foo-bar-token', ['hash' => 'foo-bar-hash']],
        ]);

        $listener = new ContextRequestListener($tokenEncoder, '1 day');
        $listener->onKernelRequest(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $this->assertSame(['hash' => 'profile-hash'], $request->attributes->get('context.profile'));
        $this->assertSame(['hash' => 'foo-bar-hash'], $request->attributes->get('context.foo-bar'));
    }

    public function testOnKernelRequestWithoutMatchingCookieShouldFail(): void
    {
        $request = new Request(cookies: ['unrelated_cookie' => 'value']);

        $tokenEncoder = $this->createMock(TokenEncoderInterface::class);
        $tokenEncoder->expects($this->never())->method('decode');

        $listener = new ContextRequestListener($tokenEncoder, '1 day');
        $listener->onKernelRequest(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $this->assertSame([], $request->attributes->all());
    }

    public function testOnKernelRequestWithInvalidTokenShouldFail(): void
    {
        $request = new Request(cookies: ['profile_context' => 'tampered-token']);

        $tokenEncoder = $this->createMock(TokenEncoderInterface::class);
        $tokenEncoder->method('decode')->with('tampered-token')->willReturn(null);

        $listener = new ContextRequestListener($tokenEncoder, '1 day');
        $listener->onKernelRequest(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $this->assertSame([], $request->attributes->all());
    }

    public function testOnKernelRequestOnSubRequestShouldFail(): void
    {
        $request = new Request(cookies: ['profile_context' => 'profile-token']);

        $tokenEncoder = $this->createMock(TokenEncoderInterface::class);
        $tokenEncoder->expects($this->never())->method('decode');

        $listener = new ContextRequestListener($tokenEncoder, '1 day');
        $listener->onKernelRequest(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::SUB_REQUEST));

        $this->assertSame([], $request->attributes->all());
    }

    public function testOnKernelRequestWithStaleTokenShouldSetRefreshFlag(): void
    {
        $request = new Request(cookies: ['profile_context' => 'profile-token']);

        $tokenEncoder = $this->createMock(TokenEncoderInterface::class);
        $tokenEncoder->method('decode')->with('profile-token')->willReturn(['hash' => 'cached']);
        $tokenEncoder->method('getIssuedAt')->with('profile-token')->willReturn(new \DateTimeImmutable('-2 days'));

        $listener = new ContextRequestListener($tokenEncoder, '1 day');
        $listener->onKernelRequest(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $this->assertTrue($request->attributes->get('context.profile.refresh'));
    }

    public function testOnKernelRequestWithFreshTokenShouldNotSetRefreshFlag(): void
    {
        $request = new Request(cookies: ['profile_context' => 'profile-token']);

        $tokenEncoder = $this->createMock(TokenEncoderInterface::class);
        $tokenEncoder->method('decode')->with('profile-token')->willReturn(['hash' => 'cached']);
        $tokenEncoder->method('getIssuedAt')->with('profile-token')->willReturn(new \DateTimeImmutable('-1 hour'));

        $listener = new ContextRequestListener($tokenEncoder, '1 day');
        $listener->onKernelRequest(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $this->assertFalse($request->attributes->has('context.profile.refresh'));
    }

    public function testOnKernelRequestWithUnknownIssuedAtShouldNotSetRefreshFlag(): void
    {
        $request = new Request(cookies: ['profile_context' => 'profile-token']);

        $tokenEncoder = $this->createMock(TokenEncoderInterface::class);
        $tokenEncoder->method('decode')->with('profile-token')->willReturn(['hash' => 'cached']);
        $tokenEncoder->method('getIssuedAt')->with('profile-token')->willReturn(null);

        $listener = new ContextRequestListener($tokenEncoder, '1 day');
        $listener->onKernelRequest(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $this->assertFalse($request->attributes->has('context.profile.refresh'));
    }
}
