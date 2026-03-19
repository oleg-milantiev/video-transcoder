<?php

namespace App\Infrastructure\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ApiTokenService
{
    private const int DEFAULT_TTL_SECONDS = 3600;

    public function __construct(
        #[Autowire('%kernel.secret%')]
        private string $secret,
        private int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
    ) {
    }

    public function createToken(int $userId, string $identifier): string
    {
        $payload = [
            'sub' => $userId,
            'identifier' => $identifier,
            'exp' => time() + $this->ttlSeconds(),
        ];

        $payloadPart = $this->base64UrlEncode((string) json_encode($payload, JSON_THROW_ON_ERROR));
        $signaturePart = $this->base64UrlEncode(hash_hmac('sha256', $payloadPart, $this->secret, true));

        return sprintf('%s.%s', $payloadPart, $signaturePart);
    }

    /**
     * @return array{sub: int, identifier: string, exp: int}
     * @throws \JsonException
     */
    public function parseToken(string $token): array
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

        if (!is_int($sub) || !is_string($identifier) || !is_int($exp)) {
            throw new \InvalidArgumentException('Invalid token claims.');
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


