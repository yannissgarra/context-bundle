<?php

/*
 * (c) Yannis Sgarra <hello@yannissgarra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Webmunkeez\ContextBundle\Context;

/**
 * @author Yannis Sgarra <hello@yannissgarra.com>
 */
interface ContextProviderInterface
{
    /**
     * @param class-string<ContextInterface> $contextClass
     */
    public function get(string $contextClass): ContextInterface;

    public function update(ContextInterface $context): void;
}
