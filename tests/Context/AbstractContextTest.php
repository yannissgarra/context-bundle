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

/**
 * @author Yannis Sgarra <hello@yannissgarra.com>
 */
final class AbstractContextTest extends TestCase
{
    public function testGetReferenceShouldSucceed(): void
    {
        $this->assertSame('foo-bar', FooBarContext::getReference());
    }

    public function testGetReferenceWithoutContextSuffixeShouldSucceed(): void
    {
        $this->assertSame('foo-bar', FooBar::getReference());
    }

    public function testGetTtlShouldReturnDefaultValue(): void
    {
        $this->assertSame('1 year', FooBarContext::getTtl());
    }

    public function testGetRefreshAfterShouldReturnDefaultValue(): void
    {
        $this->assertSame('1 day', FooBarContext::getRefreshAfter());
    }

    public function testGetTtlShouldBeOverridable(): void
    {
        $this->assertSame('30 days', CustomTtlContext::getTtl());
    }

    public function testGetRefreshAfterShouldBeOverridable(): void
    {
        $this->assertSame('2 hours', CustomTtlContext::getRefreshAfter());
    }
}
