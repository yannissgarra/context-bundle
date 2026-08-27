<?php

/*
 * (c) Yannis Sgarra <hello@yannissgarra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Webmunkeez\ContextBundle\Exception;

use Webmunkeez\ContextBundle\Context\ContextInterface;

/**
 * @author Yannis Sgarra <hello@yannissgarra.com>
 */
final class ContextClassNotFoundException extends \RuntimeException
{
    public function __construct(string $contextClass)
    {
        parent::__construct(sprintf('Context class "%s" does not exist or does not implement %s.', $contextClass, ContextInterface::class));
    }
}
