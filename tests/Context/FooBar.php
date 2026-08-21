<?php

/*
 * (c) Yannis Sgarra <hello@yannissgarra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Webmunkeez\ContextBundle\Test\Context;

use Webmunkeez\ContextBundle\Context\AbstractContext;

/**
 * @author Yannis Sgarra <hello@yannissgarra.com>
 */
final class FooBar extends AbstractContext
{
    public function getHash(): string
    {
        return '';
    }
}
