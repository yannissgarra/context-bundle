<?php

/*
 * (c) Yannis Sgarra <hello@yannissgarra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Webmunkeez\ContextBundle\Token;

/**
 * @author Yannis Sgarra <hello@yannissgarra.com>
 */
interface TokenEncoderInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function encode(array $payload): string;

    /**
     * @return array<string, mixed>|null the decoded payload, or null if the token is malformed, tampered with, or expired
     */
    public function decode(string $token): ?array;

    /**
     * @return \DateTimeImmutable|null the token's issuance time, or null if it is malformed, tampered with, expired, or carries no issuance time
     */
    public function getIssuedAt(string $token): ?\DateTimeImmutable;
}
