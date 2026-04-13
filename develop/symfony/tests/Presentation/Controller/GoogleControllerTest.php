<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller;

use App\Application\Logging\LogServiceInterface;
use App\Infrastructure\Google\GoogleAuthenticator;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Tests\Presentation\Controller\Api\InMemoryTestUserProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;
use Symfony\Component\VarExporter\LazyObjectInterface;

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

        $mockGoogleUser = $this->createStub(GoogleUser::class);
        $mockGoogleUser->method('getEmail')->willReturn('newuser@example.com');
        $mockGoogleUser->method('getEmailVerified')->willReturn(true);

        $mockAuth = $this->createStub(GoogleAuthenticator::class);
        $mockAuth->method('getUserFromCode')->willReturn($mockGoogleUser);
        static::getContainer()->set(GoogleAuthenticator::class, $mockAuth);

        // Stub EntityManager — also implements LazyObjectInterface so services_resetter
        // can call resetLazyObject() without trying to reinitialize the Doctrine lazy ghost proxy
        $mockRepo = $this->createStub(EntityRepository::class);
        $mockRepo->method('findOneBy')->willReturn(null);

        /** @var EntityManagerInterface&LazyObjectInterface $mockEm */
        $mockEm = $this->createStubForIntersectionOfInterfaces([
            EntityManagerInterface::class,
            LazyObjectInterface::class,
        ]);
        $mockEm->method('resetLazyObject')->willReturn(true);
        $mockEm->method('isLazyObjectInitialized')->willReturn(true);
        $mockEm->method('getRepository')->willReturn($mockRepo);
        $mockEm->method('getReference')->willReturnCallback(static function (string $class): object {
            return new $class();
        });
        // Use callback so we can set $user->id after persist (real DB would auto-generate it)
        $mockEm->method('persist')->willReturnCallback(static function (object $entity): void {
            if ($entity instanceof UserEntity && $entity->id === null) {
                $entity->id = SymfonyUuid::fromString('22222222-2222-4222-8222-222222222222');
            }
        });
        $mockEm->method('flush');
        static::getContainer()->set(EntityManagerInterface::class, $mockEm);

        $mockPasswordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $mockPasswordHasher->method('hashPassword')->willReturn('hashed-password');
        static::getContainer()->set(UserPasswordHasherInterface::class, $mockPasswordHasher);

        $mockSecurity = $this->createStub(Security::class);
        $mockSecurity->method('login')->willReturn(null); // Returns null to trigger redirect
        static::getContainer()->set(Security::class, $mockSecurity);

        $mockLogService = $this->createStub(LogServiceInterface::class);
        static::getContainer()->set(LogServiceInterface::class, $mockLogService);

        $client->request('GET', '/connect/google/check?state=valid-state&code=valid-code');

        self::assertResponseRedirects('/');
        self::assertResponseStatusCodeSame(302);
    }

    public function testGoogleCallbackWithValidCodeLogsInExistingUser(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        $existingUser = new UserEntity();
        $existingUser->id = SymfonyUuid::fromString('11111111-1111-4111-8111-111111111111');
        $existingUser->email = 'existing@example.com';
        $existingUser->roles = ['ROLE_USER'];

        $mockGoogleUser = $this->createStub(GoogleUser::class);
        $mockGoogleUser->method('getEmail')->willReturn('existing@example.com');
        $mockGoogleUser->method('getEmailVerified')->willReturn(true);

        $mockAuth = $this->createStub(GoogleAuthenticator::class);
        $mockAuth->method('getUserFromCode')->willReturn($mockGoogleUser);
        static::getContainer()->set(GoogleAuthenticator::class, $mockAuth);

        $mockRepo = $this->createStub(EntityRepository::class);
        $mockRepo->method('findOneBy')->willReturn($existingUser);

        /** @var EntityManagerInterface&LazyObjectInterface $mockEm */
        $mockEm = $this->createStubForIntersectionOfInterfaces([
            EntityManagerInterface::class,
            LazyObjectInterface::class,
        ]);
        $mockEm->method('resetLazyObject')->willReturn(true);
        $mockEm->method('isLazyObjectInitialized')->willReturn(true);
        $mockEm->method('getRepository')->willReturn($mockRepo);
        $mockEm->method('flush');
        static::getContainer()->set(EntityManagerInterface::class, $mockEm);

        $mockPasswordHasher = $this->createStub(UserPasswordHasherInterface::class);
        static::getContainer()->set(UserPasswordHasherInterface::class, $mockPasswordHasher);

        $mockSecurity = $this->createStub(Security::class);
        $mockSecurity->method('login')->willReturn(null); // Returns null to trigger redirect
        static::getContainer()->set(Security::class, $mockSecurity);

        $mockLogService = $this->createStub(LogServiceInterface::class);
        static::getContainer()->set(LogServiceInterface::class, $mockLogService);

        $client->request('GET', '/connect/google/check?state=valid-state&code=valid-code');

        self::assertResponseRedirects('/');
        self::assertResponseStatusCodeSame(302);
    }
}
