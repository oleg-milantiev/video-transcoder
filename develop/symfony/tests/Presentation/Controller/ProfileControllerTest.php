<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller;

use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Presentation\Controller\ProfileController;
use App\Tests\Presentation\Controller\Api\InMemoryTestUserProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

#[CoversClass(ProfileController::class)]
class ProfileControllerTest extends WebTestCase
{
    public function testProfileRedirectsToLoginForGuest(): void
    {
        $client = static::createClient();
        $client->request('GET', '/profile');

        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testProfileShowsProfilePageForAuthenticatedUser(): void
    {
        $client = static::createClient();

        $user = new UserEntity();
        $user->id = SymfonyUuid::fromString('00000000-0000-4000-8000-000000000901');
        $user->email = 'profile-user@example.com';
        $user->roles = ['ROLE_USER'];

        static::getContainer()->set('security.user.provider.concrete.app_user_provider', new InMemoryTestUserProvider($user));

        $client->loginUser($user);
        $client->request('GET', '/profile');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#home-spa'); // Profile page mounts the home SPA
    }
}
