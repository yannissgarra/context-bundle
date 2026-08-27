<?php

/*
 * (c) Yannis Sgarra <hello@yannissgarra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Webmunkeez\ContextBundle\Token;

use Webmunkeez\ContextBundle\Context\ContextInterface;

/**
 * @author Yannis Sgarra <hello@yannissgarra.com>
 */
final class ContextToken
{
    /**
     * @param array<string, mixed>           $payload
     * @param class-string<ContextInterface> $contextClass
     */
    public function __construct(
        private readonly array $payload,
        private readonly \DateTimeImmutable $issuedAt,
        private readonly string $contextClass,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getIssuedAt(): \DateTimeImmutable
    {
        return $this->issuedAt;
    }

    /**
     * @return class-string<ContextInterface>
     */
    public function getContextClass(): string
    {
        return $this->contextClass;
    }
}
