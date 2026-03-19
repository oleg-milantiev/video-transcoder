<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller\Api;

use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Security\ApiTokenService;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AuthApiControllerTest extends ApiWebTestCase
{
    /**
     * @throws \JsonException
     */
    public function testTokenReturnsBadRequestForInvalidJson(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/auth/token',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: 'not-json'
        );

        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => 'Invalid JSON payload.'], $this->decodeJson($client->getResponse()->getContent()));
    }

    /**
     * @throws \JsonException
     */
    public function testTokenReturnsBadRequestForMissingFields(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/auth/token',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['email' => ''], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => 'Email and password are required.'], $this->decodeJson($client->getResponse()->getContent()));
    }

    /**
     * @throws \JsonException
     */
    public function testTokenReturnsUnauthorizedForUnknownUser(): void
    {
        $client = static::createClient();

        $existingUser = new UserEntity();
        $existingUser->id = 7;
        $existingUser->email = 'known@example.com';
        $existingUser->roles = ['ROLE_USER'];

        $provider = new InMemoryTestUserProvider($existingUser);
        static::getContainer()->set('security.user.provider.concrete.app_user_provider', $provider);

        $client->request(
            'POST',
            '/api/auth/token',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['email' => 'missing@example.com', 'password' => 'secret'], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['error' => 'Invalid credentials.'], $this->decodeJson($client->getResponse()->getContent()));
    }

    /**
     * @throws \JsonException
     */
    public function testTokenReturnsUnauthorizedForWrongPassword(): void
    {
        $client = static::createClient();

        $user = new UserEntity();
        $user->id = 8;
        $user->email = 'known@example.com';
        $user->password = 'hash';
        $user->roles = ['ROLE_USER'];

        $provider = new InMemoryTestUserProvider($user);
        static::getContainer()->set('security.user.provider.concrete.app_user_provider', $provider);

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, 'wrong-password')
            ->willReturn(false);
        static::getContainer()->set(UserPasswordHasherInterface::class, $hasher);

        $client->request(
            'POST',
            '/api/auth/token',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['email' => 'known@example.com', 'password' => 'wrong-password'], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['error' => 'Invalid credentials.'], $this->decodeJson($client->getResponse()->getContent()));
    }

    /**
     * @throws \JsonException
     */
    public function testTokenReturnsBearerTokenForValidCredentials(): void
    {
        $client = static::createClient();

        $user = new UserEntity();
        $user->id = 9;
        $user->email = 'known@example.com';
        $user->password = 'hash';
        $user->roles = ['ROLE_USER'];

        $provider = new InMemoryTestUserProvider($user);
        static::getContainer()->set('security.user.provider.concrete.app_user_provider', $provider);

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, 'secret-password')
            ->willReturn(true);
        static::getContainer()->set(UserPasswordHasherInterface::class, $hasher);

        $client->request(
            'POST',
            '/api/auth/token',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['email' => 'known@example.com', 'password' => 'secret-password'], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(200);

        $payload = $this->decodeJson($client->getResponse()->getContent());
        self::assertSame('Bearer', $payload['tokenType']);
        self::assertIsString($payload['accessToken']);
        self::assertGreaterThan(0, $payload['expiresIn']);

        /** @var ApiTokenService $tokenService */
        $tokenService = static::getContainer()->get(ApiTokenService::class);
        $claims = $tokenService->parseToken($payload['accessToken']);

        self::assertSame(9, $claims['sub']);
        self::assertSame('known@example.com', $claims['identifier']);
    }

    /**
     * @return array<mixed>
     * @throws \JsonException
     */
    private function decodeJson(?string $content): array
    {
        self::assertNotNull($content);

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}

