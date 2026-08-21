<?php

/*
 * (c) Yannis Sgarra <hello@yannissgarra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Webmunkeez\ContextBundle\Jwt;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Webmunkeez\ContextBundle\Token\TokenEncoderInterface;

/**
 * @author Yannis Sgarra <hello@yannissgarra.com>
 */
final class JwtTokenEncoder implements TokenEncoderInterface
{
    private const ALGORITHM = 'HS256';

    public function __construct(
        private readonly string $secret,
        private readonly string $ttl,
    ) {
    }

    public function encode(array $payload): string
    {
        return JWT::encode([
            'exp' => (new \DateTimeImmutable('+'.$this->ttl))->getTimestamp(),
            'data' => $payload,
        ], $this->secret, self::ALGORITHM);
    }

    public function decode(string $token): ?array
    {
        try {
            $claims = JWT::decode($token, new Key($this->secret, self::ALGORITHM));
        } catch (\UnexpectedValueException) {
            return null;
        }

        if (false === isset($claims->data)) {
            return null;
        }

        // JWT::decode() turns JSON objects into stdClass recursively; round-trip through json to get a plain array back
        $data = json_decode((string) json_encode($claims->data), true);

        return is_array($data) ? $data : null;
    }
}
