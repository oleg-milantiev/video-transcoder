<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller\Api;

use App\Domain\Shared\ValueObject\Uuid;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Security\ApiTokenService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

abstract class ApiWebTestCase extends WebTestCase
{
    protected function createBearerAuthenticatedClient(?SymfonyUuid $userId = null, array $roles = ['ROLE_USER']): KernelBrowser
    {
        $client = static::createClient();

        $userId ??= SymfonyUuid::fromString('11111111-1111-4111-8111-111111111111');

        $user = new UserEntity();
        $user->id = $userId;
        $user->email = sprintf('api-user-%s@example.com', $userId->toRfc4122());
        $user->roles = $roles;

        $provider = new InMemoryTestUserProvider($user);
        static::getContainer()->set('security.user.provider.concrete.app_user_provider', $provider);

        /** @var ApiTokenService $tokenService */
        $tokenService = static::getContainer()->get(ApiTokenService::class);
        $token = $tokenService->createToken(Uuid::fromString($userId->toRfc4122()), $user->getUserIdentifier());

        $client->setServerParameter('HTTP_AUTHORIZATION', sprintf('Bearer %s', $token));

        return $client;
    }

    protected function replaceService(string $serviceId, object $service): void
    {
        static::getContainer()->set($serviceId, $service);
    }
}
