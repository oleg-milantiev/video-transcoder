<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Security;

use App\Domain\Shared\ValueObject\Uuid;
use App\Infrastructure\Security\ApiTokenService;
use PHPUnit\Framework\TestCase;

final class ApiTokenServiceTest extends TestCase
{
    private ApiTokenService $service;
    private Uuid $userId;

    protected function setUp(): void
    {
        $this->service = new ApiTokenService(secret: 'test-secret');
        $this->userId = Uuid::fromString('11111111-1111-4111-8111-111111111111');
    }

    public function testCreateAndParseAccessToken(): void
    {
        $token = $this->service->createToken($this->userId, 'user@example.com');
        $claims = $this->service->parseToken($token);

        self::assertSame('11111111-1111-4111-8111-111111111111', $claims['sub']);
        self::assertSame('user@example.com', $claims['identifier']);
        self::assertGreaterThan(time(), $claims['exp']);
    }

    public function testCreateAndParseRefreshToken(): void
    {
        $token = $this->service->createRefreshToken($this->userId, 'user@example.com');
        $claims = $this->service->parseRefreshToken($token);

        self::assertSame('11111111-1111-4111-8111-111111111111', $claims['sub']);
        self::assertSame('user@example.com', $claims['identifier']);
        self::assertGreaterThan(time() + 23*3600, $claims['exp']); // at least 23h left
    }

    public function testRefreshTokenHasLongerTtlThanAccessToken(): void
    {
        $accessToken = $this->service->createToken($this->userId, 'user@example.com');
        $refreshToken = $this->service->createRefreshToken($this->userId, 'user@example.com');

        $accessClaims = $this->service->parseToken($accessToken);
        $refreshClaims = $this->service->parseRefreshToken($refreshToken);

        self::assertGreaterThan($accessClaims['exp'], $refreshClaims['exp']);
    }

    public function testParseTokenRejectsRefreshToken(): void
    {
        $refreshToken = $this->service->createRefreshToken($this->userId, 'user@example.com');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid token type.');
        $this->service->parseToken($refreshToken);
    }

    public function testParseRefreshTokenRejectsAccessToken(): void
    {
        $accessToken = $this->service->createToken($this->userId, 'user@example.com');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid token type.');
        $this->service->parseRefreshToken($accessToken);
    }

    public function testParseTokenRejectsMalformedToken(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->parseToken('not-a-valid-token');
    }

    public function testParseRefreshTokenRejectsExpiredToken(): void
    {
        $service = new ApiTokenService(secret: 'test-secret', ttlSeconds: 1, refreshTtlSecondsValue: 1);
        $token = $service->createRefreshToken($this->userId, 'user@example.com');
        sleep(2);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Token expired.');
        $service->parseRefreshToken($token);
    }

    public function testParseTokenRejectsInvalidSignature(): void
    {
        $token = $this->service->createToken($this->userId, 'user@example.com');
        $tampered = $token . 'x';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid token signature.');
        $this->service->parseToken($tampered);
    }

    public function testTtlSecondsReturnsDefault(): void
    {
        self::assertSame(3600, $this->service->ttlSeconds());
    }

    public function testRefreshTtlSecondsReturnsDefault(): void
    {
        self::assertSame(86400, $this->service->refreshTtlSeconds());
    }

    public function testNegativeTtlFallsBackToDefault(): void
    {
        $service = new ApiTokenService(secret: 'test-secret', ttlSeconds: -1);
        self::assertSame(3600, $service->ttlSeconds());
    }

    public function testZeroTtlFallsBackToDefault(): void
    {
        $service = new ApiTokenService(secret: 'test-secret', ttlSeconds: 0);
        self::assertSame(3600, $service->ttlSeconds());
    }

    public function testNegativeRefreshTtlFallsBackToDefault(): void
    {
        $service = new ApiTokenService(secret: 'test-secret', refreshTtlSecondsValue: -5);
        self::assertSame(86400, $service->refreshTtlSeconds());
    }

    public function testTokenWithNegativeTtlIsStillParseable(): void
    {
        // negative ttl falls back to 3600 so token should parse fine
        $service = new ApiTokenService(secret: 'sec', ttlSeconds: -1);
        $token = $service->createToken($this->userId, 'u@example.com');
        $claims = $service->parseToken($token);

        self::assertSame($this->userId->toRfc4122(), $claims['sub']);
    }

    public function testParseTokenRejectsTokenWithInvalidPayload(): void
    {
        // Build a token with valid signature but non-JSON payload — json_decode throws JsonException
        $badPayload = rtrim(strtr(base64_encode('not-json'), '+/', '-_'), '=');
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', $badPayload, 'test-secret', true)), '+/', '-_'), '=');

        $this->expectException(\JsonException::class);
        $this->service->parseToken($badPayload . '.' . $sig);
    }

    public function testParseTokenWithNonArrayJsonPayloadThrowsInvalidArgument(): void
    {
        // Payload is valid JSON but NOT an array (a JSON integer) → !is_array($payload) branch
        $payloadPart = rtrim(strtr(base64_encode('42'), '+/', '-_'), '=');
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', $payloadPart, 'test-secret', true)), '+/', '-_'), '=');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid token payload.');
        $this->service->parseToken($payloadPart . '.' . $sig);
    }

    public function testParseTokenWithInvalidClaimsThrows(): void
    {
        // Payload is a valid JSON array but sub is not a valid UUID
        $payload = json_encode(['sub' => 'not-a-uuid', 'identifier' => 'user@test.com', 'exp' => time() + 3600, 'type' => 'access']);
        $payloadPart = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', $payloadPart, 'test-secret', true)), '+/', '-_'), '=');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid token claims.');
        $this->service->parseToken($payloadPart . '.' . $sig);
    }

    public function testParseTokenWithMissingSubThrows(): void
    {
        // Payload missing 'sub' key → null sub fails string check
        $payload = json_encode(['identifier' => 'user@test.com', 'exp' => time() + 3600, 'type' => 'access']);
        $payloadPart = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', $payloadPart, 'test-secret', true)), '+/', '-_'), '=');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid token claims.');
        $this->service->parseToken($payloadPart . '.' . $sig);
    }
}
