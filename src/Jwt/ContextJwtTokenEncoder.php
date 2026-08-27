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
use Webmunkeez\ContextBundle\Context\ContextInterface;
use Webmunkeez\ContextBundle\Exception\ContextClassNotFoundException;
use Webmunkeez\ContextBundle\Token\ContextToken;
use Webmunkeez\ContextBundle\Token\ContextTokenEncoderInterface;

/**
 * @author Yannis Sgarra <hello@yannissgarra.com>
 */
final class ContextJwtTokenEncoder implements ContextTokenEncoderInterface
{
    private const ALGORITHM = 'HS256';

    public function __construct(
        private readonly string $secret,
    ) {
    }

    public function encode(array $payload, string $contextClass): string
    {
        $ttl = $contextClass::getTtl();
        $refreshAfter = $contextClass::getRefreshAfter();

        $ttlTimestamp = strtotime('+'.$ttl);
        $refreshAfterTimestamp = strtotime('+'.$refreshAfter);

        if (false === $ttlTimestamp || false === $refreshAfterTimestamp || $refreshAfterTimestamp >= $ttlTimestamp) {
            throw new \DomainException(sprintf('%s::getTtl() and %s::getRefreshAfter() must both be valid relative date/time strings, with getRefreshAfter() shorter than getTtl().', $contextClass, $contextClass));
        }

        return JWT::encode([
            'iat' => (new \DateTimeImmutable())->getTimestamp(),
            'exp' => $ttlTimestamp,
            'ctx' => $contextClass,
            'data' => $payload,
        ], $this->secret, self::ALGORITHM);
    }

    public function decode(string $token): ?ContextToken
    {
        try {
            $claims = JWT::decode($token, new Key($this->secret, self::ALGORITHM));
        } catch (\UnexpectedValueException) {
            return null;
        }

        if (false === isset($claims->data, $claims->iat, $claims->ctx) || false === is_string($claims->ctx)) {
            return null;
        }

        $contextClass = $claims->ctx;

        if (false === class_exists($contextClass) || false === is_a($contextClass, ContextInterface::class, true)) {
            throw new ContextClassNotFoundException($contextClass);
        }

        // JWT::decode() turns JSON objects into stdClass recursively; round-trip through json to get a plain array back
        $data = json_decode((string) json_encode($claims->data), true);

        if (false === is_array($data)) {
            return null;
        }

        return new ContextToken($data, (new \DateTimeImmutable())->setTimestamp((int) $claims->iat), $contextClass);
    }
}
