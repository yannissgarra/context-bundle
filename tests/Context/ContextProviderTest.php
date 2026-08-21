<?php

/*
 * (c) Yannis Sgarra <hello@yannissgarra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Webmunkeez\ContextBundle\Test\Context;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\SerializerInterface;
use Webmunkeez\ContextBundle\Context\ContextProvider;

/**
 * @author Yannis Sgarra <hello@yannissgarra.com>
 */
final class ContextProviderTest extends TestCase
{
    public function testGetWithoutMainRequestShouldSucceed(): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getMainRequest')->willReturn(null);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->never())->method('deserialize');

        $provider = new ContextProvider($requestStack, $serializer);

        $this->assertInstanceOf(FooBarContext::class, $provider->get(FooBarContext::class));
    }

    public function testGetWithoutContextShouldSucceed(): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getMainRequest')->willReturn(new Request());

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->never())->method('deserialize');

        $provider = new ContextProvider($requestStack, $serializer);

        $this->assertInstanceOf(FooBarContext::class, $provider->get(FooBarContext::class));
    }

    public function testGetWithContextShouldSucceed(): void
    {
        $request = new Request();
        $request->attributes->set('context.'.FooBarContext::getReference(), '{"hash":"cached"}');

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getMainRequest')->willReturn($request);

        $cachedContext = new FooBarContext('cached');

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('deserialize')
            ->with('{"hash":"cached"}', FooBarContext::class, JsonEncoder::FORMAT)
            ->willReturn($cachedContext);

        $provider = new ContextProvider($requestStack, $serializer);

        $this->assertSame($cachedContext, $provider->get(FooBarContext::class));
    }

    public function testUpdateWithoutMainRequestShouldFail(): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getMainRequest')->willReturn(null);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->never())->method('serialize');

        $provider = new ContextProvider($requestStack, $serializer);

        $provider->update(new FooBarContext());
    }

    public function testUpdateWithoutContextShouldFail(): void
    {
        $request = new Request();

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getMainRequest')->willReturn($request);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->never())->method('serialize');

        $provider = new ContextProvider($requestStack, $serializer);

        // get() finds nothing cached and falls back to `new FooBarContext()`, whose default hash is ''
        $provider->update(new FooBarContext());

        $this->assertFalse($request->attributes->has('context.'.FooBarContext::getReference()));
    }

    public function testUpdateWithNewContextShouldSucceed(): void
    {
        $request = new Request();

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getMainRequest')->willReturn($request);

        $context = new FooBarContext('new-hash');

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('serialize')
            ->with($context, JsonEncoder::FORMAT)
            ->willReturn('{"hash":"new-hash"}');

        $provider = new ContextProvider($requestStack, $serializer);

        $provider->update($context);

        $this->assertSame('{"hash":"new-hash"}', $request->attributes->get('context.'.FooBarContext::getReference()));
        $this->assertTrue($request->attributes->get('context.'.FooBarContext::getReference().'.refresh'));
    }

    public function testUpdateWithSameContextShouldFail(): void
    {
        $request = new Request();
        $request->attributes->set('context.'.FooBarContext::getReference(), '{"hash":"same-hash"}');

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getMainRequest')->willReturn($request);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('deserialize')
            ->with('{"hash":"same-hash"}', FooBarContext::class, JsonEncoder::FORMAT)
            ->willReturn(new FooBarContext('same-hash'));
        $serializer->expects($this->never())->method('serialize');

        $provider = new ContextProvider($requestStack, $serializer);

        $provider->update(new FooBarContext('same-hash'));

        // the cached value must be left untouched
        $this->assertSame('{"hash":"same-hash"}', $request->attributes->get('context.'.FooBarContext::getReference()));
        $this->assertFalse($request->attributes->has('context.'.FooBarContext::getReference().'.refresh'));
    }

    public function testUpdateWithUpdatedContextShouldSucceed(): void
    {
        $request = new Request();
        $request->attributes->set('context.'.FooBarContext::getReference(), '{"hash":"old-hash"}');

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getMainRequest')->willReturn($request);

        $context = new FooBarContext('new-hash');

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('deserialize')
            ->with('{"hash":"old-hash"}', FooBarContext::class, JsonEncoder::FORMAT)
            ->willReturn(new FooBarContext('old-hash'));
        $serializer->method('serialize')
            ->with($context, JsonEncoder::FORMAT)
            ->willReturn('{"hash":"new-hash"}');

        $provider = new ContextProvider($requestStack, $serializer);

        $provider->update($context);

        $this->assertSame('{"hash":"new-hash"}', $request->attributes->get('context.'.FooBarContext::getReference()));
        $this->assertTrue($request->attributes->get('context.'.FooBarContext::getReference().'.refresh'));
    }
}
