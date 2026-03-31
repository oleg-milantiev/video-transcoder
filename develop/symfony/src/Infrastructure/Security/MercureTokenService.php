<?php
declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Shared\ValueObject\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class MercureTokenService
{
    private const int DEFAULT_TTL_SECONDS = 3600;

    public function __construct(
        #[Autowire('%env(MERCURE_SUBSCRIBER_JWT_KEY)%')]
        private string $subscriberKey,
        #[Autowire('%env(MERCURE_PUBLISHER_JWT_KEY)%')]
        private string $publisherKey,
        #[Autowire('%env(MERCURE_PUBLIC_URL)%')]
        private string $publicHubUrl,
        #[Autowire('%env(MERCURE_INTERNAL_URL)%')]
        private string $internalHubUrl,
        #[Autowire('%env(MERCURE_TOPIC_PREFIX)%')]
        private string $topicPrefix,
        #[Autowire('%env(int:MERCURE_TOKEN_TTL_SECONDS)%')]
        private int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
    ) {
    }

    public function createUserTopic(Uuid $userId): string
    {
        return rtrim($this->normalizeUrl($this->topicPrefix), '/') . '/' . $userId->toRfc4122();
    }

    public function createSubscriberTokenForUser(Uuid $userId): string
    {
        $topic = $this->createUserTopic($userId);

        return $this->createJwt([
            'mercure' => [
                'subscribe' => [$topic],
            ],
        ], $this->subscriberKey);
    }

    public function createPublisherTokenForTopic(string $topic): string
    {
        return $this->createJwt([
            'mercure' => [
                'publish' => [$topic],
            ],
        ], $this->publisherKey);
    }

    public function publicHubUrl(): string
    {
        return $this->normalizeUrl($this->publicHubUrl);
    }

    public function internalHubUrl(): string
    {
        return $this->normalizeUrl($this->internalHubUrl);
    }

    private function createJwt(array $claims, string $key): string
    {
        $now = time();
        $payload = array_merge($claims, [
            'iat' => $now,
            'exp' => $now + $this->ttlSeconds(),
        ]);

        $headerPart = $this->base64UrlEncode('{"alg":"HS256","typ":"JWT"}');
        $payloadPart = $this->base64UrlEncode((string) json_encode($payload, JSON_THROW_ON_ERROR));
        $signaturePart = $this->base64UrlEncode(hash_hmac('sha256', $headerPart . '.' . $payloadPart, $key, true));

        return sprintf('%s.%s.%s', $headerPart, $payloadPart, $signaturePart);
    }

    private function ttlSeconds(): int
    {
        return $this->ttlSeconds > 0 ? $this->ttlSeconds : self::DEFAULT_TTL_SECONDS;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function normalizeUrl(string $value): string
    {
        $normalized = trim($value);
        $normalized = preg_replace('#^((https?)://)((https?)://)+#i', '$1', $normalized) ?? $normalized;

        return $normalized;
    }
}
