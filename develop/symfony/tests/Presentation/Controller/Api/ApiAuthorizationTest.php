<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller\Api;

use PHPUnit\Framework\Attributes\DataProvider;

final class ApiAuthorizationTest extends ApiWebTestCase
{
    /**
     * @throws \JsonException
     */
    #[DataProvider('anonymousEndpointProvider')]
    public function testAnonymousUserGetsUnauthorizedJson(string $method, string $url): void
    {
        $client = static::createClient();
        $client->request($method, $url);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(
            ['error' => 'Missing Bearer token.'],
            json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    public static function anonymousEndpointProvider(): array
    {
        return [
            ['GET', '/api/video/'],
            ['GET', '/api/video/11111111-1111-4111-8111-111111111111'],
            ['POST', '/api/video/11111111-1111-4111-8111-111111111111/transcode/22222222-2222-4222-8222-222222222222'],
            ['DELETE', '/api/video/11111111-1111-4111-8111-111111111111'],
            ['GET', '/api/task/'],
            ['POST', '/api/task/33333333-3333-4333-8333-333333333333/cancel'],
        ];
    }

    /**
     * @throws \JsonException
     */
    public function testInvalidBearerTokenGetsUnauthorizedJson(): void
    {
        $client = static::createClient();
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer invalid-token');
        $client->request('GET', '/api/video/');

        self::assertResponseStatusCodeSame(401);
        self::assertSame(
            ['error' => 'Invalid or expired token.'],
            json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)
        );
    }
}
