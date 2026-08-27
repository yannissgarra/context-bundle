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
use Webmunkeez\ContextBundle\Exception\ContextClassNotFoundException;

/**
 * @author Yannis Sgarra <hello@yannissgarra.com>
 */
interface ContextTokenEncoderInterface
{
    /**
     * @param array<string, mixed>           $payload
     * @param class-string<ContextInterface> $contextClass
     */
    public function encode(array $payload, string $contextClass): string;

    /**
     * @return ContextToken|null the decoded token, or null if it is malformed, tampered with, or expired
     *
     * @throws ContextClassNotFoundException if the token is otherwise valid but names a context class that no longer exists
     */
    public function decode(string $token): ?ContextToken;
}
