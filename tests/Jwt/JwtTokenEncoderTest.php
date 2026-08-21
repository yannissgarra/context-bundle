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
use Webmunkeez\ContextBundle\Jwt\JwtTokenEncoder;

/**
 * @author Yannis Sgarra <hello@yannissgarra.com>
 */
final class JwtTokenEncoderTest extends TestCase
{
    // HS256 requires a key of at least 256 bits (32 characters)
    private const SECRET = '0123456789abcdef0123456789abcdef';

    private const OTHER_SECRET = 'fedcba9876543210fedcba9876543210';

    private const TTL = '1 year';

    public function testEncodeThenDecodeShouldReturnOriginalPayload(): void
    {
        $encoder = new JwtTokenEncoder(self::SECRET, self::TTL);

        $token = $encoder->encode(['hash' => 'cached']);

        $this->assertSame(['hash' => 'cached'], $encoder->decode($token));
    }

    public function testEncodeThenDecodeWithNestedPayloadShouldReturnOriginalPayload(): void
    {
        $encoder = new JwtTokenEncoder(self::SECRET, self::TTL);

        $payload = [
            'hash' => 'cached',
            'profiles' => [
                ['name' => 'Alice', 'age' => 30],
                ['name' => 'Bob', 'age' => 25],
            ],
        ];

        $token = $encoder->encode($payload);

        $this->assertSame($payload, $encoder->decode($token));
    }

    public function testEncodeUsesConfiguredTtlShouldSucceed(): void
    {
        $encoder = new JwtTokenEncoder(self::SECRET, '1 hour');

        $token = $encoder->encode(['hash' => 'cached']);

        $claims = JWT::decode($token, new Key(self::SECRET, 'HS256'));
        $expectedExp = (new \DateTimeImmutable('+1 hour'))->getTimestamp();

        $this->assertLessThanOrEqual(2, abs($claims->exp - $expectedExp));
    }

    public function testDecodeWithWrongSecretShouldFail(): void
    {
        $token = (new JwtTokenEncoder(self::SECRET, self::TTL))->encode(['hash' => 'cached']);

        $this->assertNull((new JwtTokenEncoder(self::OTHER_SECRET, self::TTL))->decode($token));
    }

    public function testDecodeWithTamperedTokenShouldFail(): void
    {
        $encoder = new JwtTokenEncoder(self::SECRET, self::TTL);

        $token = $encoder->encode(['hash' => 'cached']);

        $this->assertNull($encoder->decode($token.'tampered'));
    }

    public function testDecodeWithMalformedTokenShouldFail(): void
    {
        $this->assertNull((new JwtTokenEncoder(self::SECRET, self::TTL))->decode('not-a-jwt'));
    }

    public function testDecodeWithExpiredTokenShouldFail(): void
    {
        $token = JWT::encode(['exp' => time() - 10, 'data' => ['hash' => 'cached']], self::SECRET, 'HS256');

        $this->assertNull((new JwtTokenEncoder(self::SECRET, self::TTL))->decode($token));
    }

    public function testDecodeWithoutDataClaimShouldFail(): void
    {
        $token = JWT::encode(['exp' => time() + 10], self::SECRET, 'HS256');

        $this->assertNull((new JwtTokenEncoder(self::SECRET, self::TTL))->decode($token));
    }

    public function testDecodeWithNonArrayDataClaimShouldFail(): void
    {
        $token = JWT::encode(['exp' => time() + 10, 'data' => 'not-an-array'], self::SECRET, 'HS256');

        $this->assertNull((new JwtTokenEncoder(self::SECRET, self::TTL))->decode($token));
    }
}
