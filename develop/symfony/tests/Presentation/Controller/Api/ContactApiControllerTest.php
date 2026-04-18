<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller\Api;

final class ContactApiControllerTest extends ApiWebTestCase
{
    /**
     * @throws \JsonException
     */
    public function testAnonymousUserGetsUnauthorized(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/contact', [], [], [], json_encode(['message' => 'hello'], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @throws \JsonException
     */
    public function testEmptyMessageReturnsBadRequest(): void
    {
        $client = $this->createBearerAuthenticatedClient();
        $client->request(
            'POST',
            '/api/contact',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['message' => '   '], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(400);
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('error', $body);
    }

    /**
     * @throws \JsonException
     */
    public function testValidMessageReturns204(): void
    {
        $client = $this->createBearerAuthenticatedClient();
        $client->request(
            'POST',
            '/api/contact',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['message' => 'I would like to learn more about Enterprise plan.'], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(204);
        self::assertSame('', $client->getResponse()->getContent());
    }

    /**
     * @throws \JsonException
     */
    public function testTooLongMessageReturnsBadRequest(): void
    {
        $client = $this->createBearerAuthenticatedClient();
        $client->request(
            'POST',
            '/api/contact',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['message' => str_repeat('a', 1001)], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(400);
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('error', $body);
    }
}
