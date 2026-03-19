<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller\Api;

use PHPUnit\Framework\Attributes\DataProvider;

final class ApiAuthorizationTest extends ApiWebTestCase
{
    #[DataProvider('anonymousEndpointProvider')]
    public function testAnonymousUserIsRedirectedToLogin(string $method, string $url): void
    {
        $client = ApiAuthorizationTest::createClient();
        $client->request($method, $url);

        $response = $client->getResponse();

        self::assertTrue($response->isRedirect(), 'Anonymous API request should be redirected to login.');
        self::assertStringContainsString('/login', (string) $response->headers->get('Location'));
    }

    public static function anonymousEndpointProvider(): array
    {
        return [
            ['GET', '/api/video/'],
            ['POST', '/api/video/11111111-1111-4111-8111-111111111111/transcode/1'],
            ['GET', '/api/task/'],
            ['POST', '/api/task/1/cancel'],
        ];
    }
}

