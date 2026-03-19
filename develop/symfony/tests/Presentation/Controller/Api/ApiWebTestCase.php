<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller\Api;

use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class ApiWebTestCase extends WebTestCase
{
    protected function createAuthenticatedClient(int $userId = 1, array $roles = ['ROLE_USER']): KernelBrowser
    {
        $client = static::createClient();

        $user = new UserEntity();
        $user->id = $userId;
        $user->email = sprintf('api-user-%d@example.com', $userId);
        $user->roles = $roles;

        $provider = new InMemoryTestUserProvider($user);
        static::getContainer()->set('security.user.provider.concrete.app_user_provider', $provider);

        $client->loginUser($user);

        return $client;
    }

    protected function replaceService(string $serviceId, object $service): void
    {
        static::getContainer()->set($serviceId, $service);
    }
}



