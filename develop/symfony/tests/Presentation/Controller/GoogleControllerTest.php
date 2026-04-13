<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller;

use App\Infrastructure\Google\GoogleAuthenticator;
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

    public function testGoogleConnectRedirectsToGoogle(): void
    {
        $client = static::createClient();
        $client->request('GET', '/connect/google');

        self::assertResponseRedirects();
        $location = $client->getResponse()->headers->get('Location');
        self::assertNotNull($location);
        self::assertStringContainsString('accounts.google.com', $location);
        self::assertStringContainsString('client_id=', $location);
        self::assertStringContainsString('scope=', $location);
        self::assertStringContainsString('state=', $location);
        self::assertStringContainsString('response_type=code', $location);
    }

    public function testGoogleCallbackWithInvalidStateShowsFlashOnLoginPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/connect/google/check?state=invalid-state&code=fake-code');

        self::assertResponseRedirects('/login');

        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.alert.alert-danger');
        self::assertSelectorTextContains('.alert.alert-danger', 'Google login error. Try again later');
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

    public function testConnectRedirectsToLoginWhenGoogleAuthFails(): void
    {
        $client = static::createClient();

        $mockAuth = $this->createMock(GoogleAuthenticator::class);
        $mockAuth->expects($this->once())->method('getAuthorizationUrl')->willThrowException(new \RuntimeException('Google error'));
        static::getContainer()->set(GoogleAuthenticator::class, $mockAuth);

        $client->request('GET', '/connect/google');

        self::assertResponseRedirects('/login');

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.alert.alert-danger', 'Google connect error');
    }

    public function testConnectWithTargetPathSavesItInSession(): void
    {
        $client = static::createClient();

        $mockAuth = $this->createMock(GoogleAuthenticator::class);
        $mockAuth->expects($this->once())->method('getAuthorizationUrl')->willThrowException(new \RuntimeException('Google error'));
        static::getContainer()->set(GoogleAuthenticator::class, $mockAuth);

        $client->request('GET', '/connect/google?_target_path=%2Fvideos');

        // Symfony generates the redirect with the path unencoded in the query string
        self::assertResponseRedirects('/login?_target_path=/videos');
    }

    public function testCheckErrorIncludesTargetPathFromSession(): void
    {
        $client = static::createClient();
        $client->disableReboot(); // Keep same kernel so session + mock persist between requests

        // Mock: getAuthorizationUrl fails (connect) AND getUserFromCode fails (check)
        $mockAuth = $this->createStub(GoogleAuthenticator::class);
        $mockAuth->method('getAuthorizationUrl')->willThrowException(new \RuntimeException('Google error'));
        $mockAuth->method('getUserFromCode')->willThrowException(new \RuntimeException('Google check error'));
        static::getContainer()->set(GoogleAuthenticator::class, $mockAuth);

        // Request 1: connect with target_path → saves target path to session, redirects
        $client->request('GET', '/connect/google?_target_path=/videos');
        self::assertResponseRedirects('/login?_target_path=/videos');

        // Request 2: check → GoogleAuth fails → catch block reads target path from session
        $client->request('GET', '/connect/google/check?state=invalid&code=test');

        // The catch block finds target path in session and adds it to the redirect
        self::assertResponseRedirects('/login?_target_path=/videos');
    }

    public function testGoogleCallbackWithValidCodeCreatesNewUserAndLogsIn(): void
    {
        $client = static::createClient();
        $client->disableReboot(); // Keep same kernel so session + mock persist between requests

        // Mock GoogleAuthenticator to return a valid Google user
        $mockGoogleUser = $this->createMock(\Google\Client::class);
        $mockGoogleUser->method('getEmail')->willReturn('newuser@example.com');
        $mockGoogleUser->method('getEmailVerified')->willReturn(true);

        $mockAuth = $this->createMock(GoogleAuthenticator::class);
        $mockAuth->method('getUserFromCode')->willReturn($mockGoogleUser);
        static::getContainer()->set(GoogleAuthenticator::class, $mockAuth);

        // Mock EntityManager to return null for findOneBy (user doesn't exist yet)
        $mockEm = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $mockEm->method('getRepository')
            ->willReturnSelf();
        $mockEm->method('findOneBy')
            ->willReturn(null);
        $mockEm->method('getReference')
            ->willReturnSelf();
        $mockEm->method('persist')->willReturn(null);
        $mockEm->method('flush')->willReturn(null);
        static::getContainer()->set(\Doctrine\ORM\EntityManagerInterface::class, $mockEm);

        // Mock PasswordHasher
        $mockPasswordHasher = $this->createMock(\Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface::class);
        $mockPasswordHasher->method('hashPassword')->willReturn('hashed-password');
        static::getContainer()->set(\Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface::class, $mockPasswordHasher);

        // Mock Security service
        $mockSecurity = $this->createMock(\Symfony\Bundle\SecurityBundle\Security::class);
        $mockSecurity->method('login')->willReturn(null); // Returns null to trigger redirect
        static::getContainer()->set(\Symfony\Bundle\SecurityBundle\Security::class, $mockSecurity);

        // Mock LogService
        $mockLogService = $this->createMock(\App\Application\Logging\LogServiceInterface::class);
        $mockLogService->method('log')->willReturn(null);
        static::getContainer()->set(\App\Application\Logging\LogServiceInterface::class, $mockLogService);

        $client->request('GET', '/connect/google/check?state=valid-state&code=valid-code');

        // Should redirect to home page (no target path in session)
        self::assertResponseRedirects('/');
        self::assertResponseStatusCodeSame(302);
    }

    public function testGoogleCallbackWithValidCodeLogsInExistingUser(): void
    {
        $client = static::createClient();
        $client->disableReboot(); // Keep same kernel so session + mock persist between requests

        // Create a test user entity
        $existingUser = new \App\Infrastructure\Persistence\Doctrine\User\UserEntity();
        $existingUser->id = SymfonyUuid::fromString('11111111-1111-4111-8111-111111111111');
        $existingUser->email = 'existing@example.com';
        $existingUser->roles = ['ROLE_USER'];

        // Mock GoogleAuthenticator to return a valid Google user matching existing user
        $mockGoogleUser = $this->createMock(\Google\Client::class);
        $mockGoogleUser->method('getEmail')->willReturn('existing@example.com');
        $mockGoogleUser->method('getEmailVerified')->willReturn(true);

        $mockAuth = $this->createMock(GoogleAuthenticator::class);
        $mockAuth->method('getUserFromCode')->willReturn($mockGoogleUser);
        static::getContainer()->set(GoogleAuthenticator::class, $mockAuth);

        // Mock EntityManager to return the existing user
        $mockEm = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $mockEm->method('getRepository')
            ->willReturnSelf();
        $mockEm->method('findOneBy')
            ->willReturn($existingUser);
        $mockEm->method('flush')->willReturn(null);
        static::getContainer()->set(\Doctrine\ORM\EntityManagerInterface::class, $mockEm);

        // Mock PasswordHasher (won't be called for existing user, but set anyway)
        $mockPasswordHasher = $this->createMock(\Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface::class);
        static::getContainer()->set(\Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface::class, $mockPasswordHasher);

        // Mock Security service
        $mockSecurity = $this->createMock(\Symfony\Bundle\SecurityBundle\Security::class);
        $mockSecurity->method('login')->willReturn(null); // Returns null to trigger redirect
        static::getContainer()->set(\Symfony\Bundle\SecurityBundle\Security::class, $mockSecurity);

        // Mock LogService
        $mockLogService = $this->createMock(\App\Application\Logging\LogServiceInterface::class);
        $mockLogService->method('log')->willReturn(null);
        static::getContainer()->set(\App\Application\Logging\LogServiceInterface::class, $mockLogService);

        $client->request('GET', '/connect/google/check?state=valid-state&code=valid-code');

        // Should redirect to home page (no target path in session)
        self::assertResponseRedirects('/');
        self::assertResponseStatusCodeSame(302);
    }
}
