<?php

/*
 * (c) Yannis Sgarra <hello@yannissgarra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Webmunkeez\ContextBundle\Test\Jwt;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\TestCase;
use Webmunkeez\ContextBundle\Exception\ContextClassNotFoundException;
use Webmunkeez\ContextBundle\Jwt\ContextJwtTokenEncoder;
use Webmunkeez\ContextBundle\Test\Context\CustomTtlContext;
use Webmunkeez\ContextBundle\Test\Context\FooBarContext;
use Webmunkeez\ContextBundle\Test\Context\InvalidTtlContext;

/**
 * @author Yannis Sgarra <hello@yannissgarra.com>
 */
final class ContextJwtTokenEncoderTest extends TestCase
{
    // HS256 requires a key of at least 256 bits (32 characters)
    private const SECRET = '0123456789abcdef0123456789abcdef';

    private const OTHER_SECRET = 'fedcba9876543210fedcba9876543210';

    public function testEncodeThenDecodeShouldReturnOriginalPayload(): void
    {
        $encoder = new ContextJwtTokenEncoder(self::SECRET);

        $token = $encoder->encode(['hash' => 'cached'], FooBarContext::class);

        $this->assertSame(['hash' => 'cached'], $encoder->decode($token)?->getPayload());
    }

    public function testEncodeThenDecodeWithNestedPayloadShouldReturnOriginalPayload(): void
    {
        $encoder = new ContextJwtTokenEncoder(self::SECRET);

        $payload = [
            'hash' => 'cached',
            'profiles' => [
                ['name' => 'Alice', 'age' => 30],
                ['name' => 'Bob', 'age' => 25],
            ],
        ];

        $token = $encoder->encode($payload, FooBarContext::class);

        $this->assertSame($payload, $encoder->decode($token)?->getPayload());
    }

    public function testEncodeUsesContextClassTtlShouldSucceed(): void
    {
        $encoder = new ContextJwtTokenEncoder(self::SECRET);

        $token = $encoder->encode(['hash' => 'cached'], CustomTtlContext::class);

        $claims = JWT::decode($token, new Key(self::SECRET, 'HS256'));
        $expectedExp = (new \DateTimeImmutable('+'.CustomTtlContext::getTtl()))->getTimestamp();

        $this->assertLessThanOrEqual(2, abs($claims->exp - $expectedExp));
    }

    public function testEncodeWithRefreshAfterNotShorterThanTtlShouldThrowException(): void
    {
        $this->expectException(\DomainException::class);

        (new ContextJwtTokenEncoder(self::SECRET))->encode(['hash' => 'cached'], InvalidTtlContext::class);
    }

    public function testDecodeWithWrongSecretShouldFail(): void
    {
        $token = (new ContextJwtTokenEncoder(self::SECRET))->encode(['hash' => 'cached'], FooBarContext::class);

        $this->assertNull((new ContextJwtTokenEncoder(self::OTHER_SECRET))->decode($token));
    }

    public function testDecodeWithTamperedTokenShouldFail(): void
    {
        $encoder = new ContextJwtTokenEncoder(self::SECRET);

        $token = $encoder->encode(['hash' => 'cached'], FooBarContext::class);

        $this->assertNull($encoder->decode($token.'tampered'));
    }

    public function testDecodeWithMalformedTokenShouldFail(): void
    {
        $this->assertNull((new ContextJwtTokenEncoder(self::SECRET))->decode('not-a-jwt'));
    }

    public function testDecodeWithExpiredTokenShouldFail(): void
    {
        $token = JWT::encode(['exp' => time() - 10, 'iat' => time(), 'ctx' => FooBarContext::class, 'data' => ['hash' => 'cached']], self::SECRET, 'HS256');

        $this->assertNull((new ContextJwtTokenEncoder(self::SECRET))->decode($token));
    }

    public function testDecodeWithoutDataClaimShouldFail(): void
    {
        $token = JWT::encode(['exp' => time() + 10, 'iat' => time(), 'ctx' => FooBarContext::class], self::SECRET, 'HS256');

        $this->assertNull((new ContextJwtTokenEncoder(self::SECRET))->decode($token));
    }

    public function testDecodeWithNonArrayDataClaimShouldFail(): void
    {
        $token = JWT::encode(['exp' => time() + 10, 'iat' => time(), 'ctx' => FooBarContext::class, 'data' => 'not-an-array'], self::SECRET, 'HS256');

        $this->assertNull((new ContextJwtTokenEncoder(self::SECRET))->decode($token));
    }

    public function testDecodeWithoutCtxClaimShouldFail(): void
    {
        $token = JWT::encode(['exp' => time() + 10, 'iat' => time(), 'data' => ['hash' => 'cached']], self::SECRET, 'HS256');

        $this->assertNull((new ContextJwtTokenEncoder(self::SECRET))->decode($token));
    }

    public function testDecodeWithUnknownContextClassShouldThrowException(): void
    {
        $token = JWT::encode(['exp' => time() + 10, 'iat' => time(), 'ctx' => 'Not\\A\\Real\\Class', 'data' => ['hash' => 'cached']], self::SECRET, 'HS256');

        $this->expectException(ContextClassNotFoundException::class);

        (new ContextJwtTokenEncoder(self::SECRET))->decode($token);
    }

    public function testDecodeWithNonContextClassShouldThrowException(): void
    {
        $token = JWT::encode(['exp' => time() + 10, 'iat' => time(), 'ctx' => self::class, 'data' => ['hash' => 'cached']], self::SECRET, 'HS256');

        $this->expectException(ContextClassNotFoundException::class);

        (new ContextJwtTokenEncoder(self::SECRET))->decode($token);
    }

    public function testDecodeReturnsIssuedAtShouldSucceed(): void
    {
        $encoder = new ContextJwtTokenEncoder(self::SECRET);

        $token = $encoder->encode(['hash' => 'cached'], FooBarContext::class);

        $issuedAt = $encoder->decode($token)?->getIssuedAt();

        $this->assertNotNull($issuedAt);
        $this->assertLessThanOrEqual(2, abs($issuedAt->getTimestamp() - time()));
    }

    public function testDecodeReturnsContextClassShouldSucceed(): void
    {
        $encoder = new ContextJwtTokenEncoder(self::SECRET);

        $token = $encoder->encode(['hash' => 'cached'], FooBarContext::class);

        $this->assertSame(FooBarContext::class, $encoder->decode($token)?->getContextClass());
    }
}
