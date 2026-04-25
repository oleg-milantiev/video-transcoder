<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Security;

use App\Domain\Shared\ValueObject\Uuid;
use App\Infrastructure\Security\MercureTokenService;
use PHPUnit\Framework\TestCase;

final class MercureTokenServiceTest extends TestCase
{
    private MercureTokenService $service;
    private Uuid $userId;

    protected function setUp(): void
    {
        $this->service = new MercureTokenService(
            subscriberKey: 'sub-secret',
            publisherKey: 'pub-secret',
            publicHubUrl: 'https://mercure.example.com/.well-known/mercure',
            internalHubUrl: 'http://mercure:3000/.well-known/mercure',
            topicPrefix: 'https://example.com/users',
            ttlSeconds: 3600,
        );
        $this->userId = Uuid::fromString('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa');
    }

    public function testCreateSubscriberTokenReturnsJwt(): void
    {
        $token = $this->service->createSubscriberTokenForUser($this->userId);

        $parts = explode('.', $token);
        self::assertCount(3, $parts, 'JWT must have 3 parts');
    }

    public function testSubscriberTokenContainsUserTopic(): void
    {
        $token = $this->service->createSubscriberTokenForUser($this->userId);
        $parts = explode('.', $token);

        $payload = json_decode((string)base64_decode(str_pad(strtr($parts[1], '-_', '+/'), strlen($parts[1]) % 4 === 0 ? strlen($parts[1]) : strlen($parts[1]) + (4 - strlen($parts[1]) % 4), '=')), true);

        self::assertArrayHasKey('mercure', $payload);
        self::assertArrayHasKey('subscribe', $payload['mercure']);

        $topic = $this->service->createUserTopic($this->userId);
        self::assertContains($topic, $payload['mercure']['subscribe']);
    }

    public function testCreatePublisherTokenReturnsJwt(): void
    {
        $token = $this->service->createPublisherTokenForTopic('https://example.com/users/123');

        $parts = explode('.', $token);
        self::assertCount(3, $parts, 'JWT must have 3 parts');
    }

    public function testPublisherTokenContainsTopic(): void
    {
        $topic = 'https://example.com/users/test-topic';
        $token = $this->service->createPublisherTokenForTopic($topic);
        $parts = explode('.', $token);

        $payload = json_decode((string)base64_decode(str_pad(strtr($parts[1], '-_', '+/'), strlen($parts[1]) % 4 === 0 ? strlen($parts[1]) : strlen($parts[1]) + (4 - strlen($parts[1]) % 4), '=')), true);

        self::assertArrayHasKey('mercure', $payload);
        self::assertArrayHasKey('publish', $payload['mercure']);
        self::assertContains($topic, $payload['mercure']['publish']);
    }

    public function testCreateUserTopicBuildsCorrectUrl(): void
    {
        $topic = $this->service->createUserTopic($this->userId);

        self::assertStringEndsWith('/aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', $topic);
        self::assertStringStartsWith('https://example.com/users', $topic);
    }

    public function testPublicHubUrlNormalized(): void
    {
        $url = $this->service->publicHubUrl();

        self::assertSame('https://mercure.example.com/.well-known/mercure', $url);
    }

    public function testInternalHubUrlNormalized(): void
    {
        $url = $this->service->internalHubUrl();

        self::assertSame('http://mercure:3000/.well-known/mercure', $url);
    }

    public function testDoubleSchemeUrlNormalized(): void
    {
        $service = new MercureTokenService(
            subscriberKey: 'key',
            publisherKey: 'key',
            publicHubUrl: 'https://https://mercure.example.com/.well-known/mercure',
            internalHubUrl: 'http://mercure:3000/.well-known/mercure',
            topicPrefix: 'https://example.com/users',
        );

        self::assertSame('https://mercure.example.com/.well-known/mercure', $service->publicHubUrl());
    }

    public function testTokenHasExpiryClaim(): void
    {
        $token = $this->service->createSubscriberTokenForUser($this->userId);
        $parts = explode('.', $token);

        $pad = strlen($parts[1]) % 4;
        $padded = $pad ? $parts[1] . str_repeat('=', 4 - $pad) : $parts[1];
        $payload = json_decode((string)base64_decode(strtr($padded, '-_', '+/')), true);

        self::assertArrayHasKey('exp', $payload);
        self::assertGreaterThan(time(), $payload['exp']);
    }
}
