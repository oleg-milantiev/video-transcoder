<?php

namespace App\Infrastructure\Security;

use App\Domain\Shared\ValueObject\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ApiTokenService
{
    private const int DEFAULT_TTL_SECONDS = 3600; // 1 hour
    private const int DEFAULT_REFRESH_TTL_SECONDS = 86400; // 24 hours
    private const string TYPE_ACCESS = 'access';
    private const string TYPE_REFRESH = 'refresh';

    public function __construct(
        #[Autowire('%kernel.secret%')]
        private string $secret,
        private int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
        private int $refreshTtlSecondsValue = self::DEFAULT_REFRESH_TTL_SECONDS,
    ) {
    }

    public function createToken(Uuid $userId, string $identifier): string
    {
        return $this->buildToken($userId, $identifier, $this->ttlSeconds(), self::TYPE_ACCESS);
    }

    public function createRefreshToken(Uuid $userId, string $identifier): string
    {
        return $this->buildToken($userId, $identifier, $this->refreshTtlSeconds(), self::TYPE_REFRESH);
    }

    private function buildToken(Uuid $userId, string $identifier, int $ttl, string $type): string
    {
        $payload = [
            'sub' => $userId->toRfc4122(),
            'identifier' => $identifier,
            'exp' => time() + $ttl,
            'type' => $type,
        ];

        $payloadPart = $this->base64UrlEncode((string) json_encode($payload, JSON_THROW_ON_ERROR));
        $signaturePart = $this->base64UrlEncode(hash_hmac('sha256', $payloadPart, $this->secret, true));

        return sprintf('%s.%s', $payloadPart, $signaturePart);
    }

    /**
     * @return array{sub: string, identifier: string, exp: int}
     * @throws \JsonException
     */
    public function parseToken(string $token): array
    {
        return $this->parseAndValidate($token, self::TYPE_ACCESS);
    }

    /**
     * @return array{sub: string, identifier: string, exp: int}
     * @throws \JsonException
     */
    public function parseRefreshToken(string $token): array
    {
        return $this->parseAndValidate($token, self::TYPE_REFRESH);
    }

    /**
     * @return array{sub: string, identifier: string, exp: int}
     * @throws \JsonException
     */
    private function parseAndValidate(string $token, string $expectedType): array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new \InvalidArgumentException('Malformed token.');
        }

        [$payloadPart, $signaturePart] = $parts;
        $expectedSignature = $this->base64UrlEncode(hash_hmac('sha256', $payloadPart, $this->secret, true));

        if (!hash_equals($expectedSignature, $signaturePart)) {
            throw new \InvalidArgumentException('Invalid token signature.');
        }

        $payloadJson = $this->base64UrlDecode($payloadPart);
        $payload = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($payload)) {
            throw new \InvalidArgumentException('Invalid token payload.');
        }

        $sub = $payload['sub'] ?? null;
        $identifier = $payload['identifier'] ?? null;
        $exp = $payload['exp'] ?? null;
        $type = $payload['type'] ?? null;

        if (!is_string($sub) || !Uuid::isValid($sub) || !is_string($identifier) || !is_int($exp)) {
            throw new \InvalidArgumentException('Invalid token claims.');
        }

        if ($type !== $expectedType) {
            throw new \InvalidArgumentException('Invalid token type.');
        }

        if ($exp < time()) {
            throw new \InvalidArgumentException('Token expired.');
        }

        return [
            'sub' => $sub,
            'identifier' => $identifier,
            'exp' => $exp,
        ];
    }

    public function ttlSeconds(): int
    {
        return $this->ttlSeconds > 0 ? $this->ttlSeconds : self::DEFAULT_TTL_SECONDS;
    }

    public function refreshTtlSeconds(): int
    {
        return $this->refreshTtlSecondsValue > 0 ? $this->refreshTtlSecondsValue : self::DEFAULT_REFRESH_TTL_SECONDS;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new \InvalidArgumentException('Invalid base64 token part.');
        }

        return $decoded;
    }
}


