<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller;

use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Tests\Presentation\Controller\Api\InMemoryTestUserProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

final class GoogleControllerTest extends WebTestCase
{
    public function testLoginPageShowsGoogleSignInLink(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/connect/google"]');
        self::assertSelectorTextContains('a[href="/connect/google"]', 'Google');
    }

    public function testGoogleCallbackWithInvalidStateShowsFlashOnLoginPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/connect/google/check?state=invalid-state&code=fake-code');

        self::assertResponseRedirects('/login');

        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.alert.alert-danger');
        self::assertSelectorTextContains('.alert.alert-danger', 'Ошибка при входе через Google');
        self::assertSelectorTextContains('.alert.alert-danger', 'Invalid OAuth state');
    }

    public function testGoogleConnectWithoutConfigurationRedirectsToLoginWithFlash(): void
    {
        $client = static::createClient();
        $client->request('GET', '/connect/google');

        self::assertResponseRedirects('/login');
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.alert.alert-danger');
        self::assertSelectorTextContains('.alert.alert-danger', 'Google OAuth is not configured');
    }

    public function testAuthenticatedUserIsRedirectedFromGoogleConnectToHome(): void
    {
        $client = static::createClient();

        $user = new UserEntity();
        $user->id = SymfonyUuid::fromString('00000000-0000-4000-8000-000000000301');
        $user->email = 'google-user@example.com';
        $user->roles = ['ROLE_USER'];

        static::getContainer()->set('security.user.provider.concrete.app_user_provider', new InMemoryTestUserProvider($user));

        $client->loginUser($user);
        $client->request('GET', '/connect/google');

        self::assertResponseRedirects('/');
    }
}
