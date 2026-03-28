<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller\Api;

use App\Application\Logging\LogServiceInterface;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Security\ApiTokenService;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

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
        $existingUser->id = SymfonyUuid::fromString('00000000-0000-4000-8000-000000000007');
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
        $user->id = SymfonyUuid::fromString('00000000-0000-4000-8000-000000000008');
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
        $user->id = SymfonyUuid::fromString('00000000-0000-4000-8000-000000000009');
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

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->once())->method('log');
        static::getContainer()->set(LogServiceInterface::class, $logService);

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
        self::assertIsString($payload['refreshToken']);
        self::assertGreaterThan(0, $payload['expiresIn']);

        /** @var ApiTokenService $tokenService */
        $tokenService = static::getContainer()->get(ApiTokenService::class);
        $claims = $tokenService->parseToken($payload['accessToken']);

        self::assertSame('00000000-0000-4000-8000-000000000009', $claims['sub']);
        self::assertSame('known@example.com', $claims['identifier']);

        $refreshClaims = $tokenService->parseRefreshToken($payload['refreshToken']);
        self::assertSame('00000000-0000-4000-8000-000000000009', $refreshClaims['sub']);
        self::assertSame('known@example.com', $refreshClaims['identifier']);
    }

    // ── /refresh endpoint ───────────────────────────────────────────────────

    /**
     * @throws \JsonException
     */
    public function testRefreshReturnsBadRequestForInvalidJson(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/auth/refresh',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: 'not-json'
        );

        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => 'Invalid JSON payload.'], $this->decodeJson($client->getResponse()->getContent()));
    }

    /**
     * @throws \JsonException
     */
    public function testRefreshReturnsBadRequestForMissingRefreshToken(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/auth/refresh',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode([], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => 'refreshToken is required.'], $this->decodeJson($client->getResponse()->getContent()));
    }

    /**
     * @throws \JsonException
     */
    public function testRefreshReturnsUnauthorizedForInvalidToken(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/auth/refresh',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['refreshToken' => 'bad.token'], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['error' => 'Invalid or expired refresh token.'], $this->decodeJson($client->getResponse()->getContent()));
    }

    /**
     * @throws \JsonException
     */
    public function testRefreshReturnsNewTokenPairForValidRefreshToken(): void
    {
        $client = static::createClient();

        $user = new UserEntity();
        $user->id = SymfonyUuid::fromString('00000000-0000-4000-8000-000000000010');
        $user->email = 'refresh@example.com';
        $user->roles = ['ROLE_USER'];

        $provider = new InMemoryTestUserProvider($user);
        static::getContainer()->set('security.user.provider.concrete.app_user_provider', $provider);

        /** @var ApiTokenService $tokenService */
        $tokenService = static::getContainer()->get(ApiTokenService::class);

        $userId = \App\Domain\Shared\ValueObject\Uuid::fromString($user->id->toRfc4122());
        $refreshToken = $tokenService->createRefreshToken($userId, $user->getUserIdentifier());

        $client->request(
            'POST',
            '/api/auth/refresh',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['refreshToken' => $refreshToken], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(200);

        $payload = $this->decodeJson($client->getResponse()->getContent());
        self::assertSame('Bearer', $payload['tokenType']);
        self::assertIsString($payload['accessToken']);
        self::assertIsString($payload['refreshToken']);
        self::assertGreaterThan(0, $payload['expiresIn']);

        $accessClaims = $tokenService->parseToken($payload['accessToken']);
        self::assertSame('00000000-0000-4000-8000-000000000010', $accessClaims['sub']);
        self::assertSame('refresh@example.com', $accessClaims['identifier']);

        $refreshClaims = $tokenService->parseRefreshToken($payload['refreshToken']);
        self::assertSame('00000000-0000-4000-8000-000000000010', $refreshClaims['sub']);
        self::assertSame('refresh@example.com', $refreshClaims['identifier']);
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

