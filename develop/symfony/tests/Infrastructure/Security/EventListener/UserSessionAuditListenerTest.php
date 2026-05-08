<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Security\EventListener;

use App\Application\Logging\LogServiceInterface;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Security\EventListener\UserSessionAuditListener;
use App\Infrastructure\Security\SodiumEncryptionService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

final class UserSessionAuditListenerTest extends TestCase
{
    private const string TEST_PUBLIC_KEY  = 'C7a5OawBVhVgn6E7X5TkRYAOa+lBN5VaZgVlnpAmUCw=';
    private const string TEST_PRIVATE_KEY = '04bGdD/HeL66V5wZBqa3AZGf3VIMMgODT75h2x2606Q=';

    private function makeUser(): UserEntity
    {
        $user = new UserEntity();
        $user->id = SymfonyUuid::fromString('11111111-1111-4111-8111-111111111111');
        $user->email = 'user@example.com';
        $user->roles = ['ROLE_USER'];
        $user->profile = [];

        return $user;
    }

    private function makeListener(
        ?EntityManagerInterface $em = null,
        ?SodiumEncryptionService $sodium = null,
        string $cfPublicKey = self::TEST_PUBLIC_KEY,
    ): UserSessionAuditListener {
        return new UserSessionAuditListener(
            $this->createStub(LogServiceInterface::class),
            $em ?? $this->createStub(EntityManagerInterface::class),
            $sodium ?? new SodiumEncryptionService(),
            $cfPublicKey,
        );
    }

    private function makeLoginEvent(UserEntity $user, string $firewall, Request $request): LoginSuccessEvent
    {
        $token = new PostAuthenticationToken($user, $firewall, $user->roles);

        $authenticator = $this->createStub(AuthenticatorInterface::class);

        // LoginSuccessEvent::getUser() delegates to passport->getUser(), so configure it to
        // return the concrete UserEntity used in the test.
        $passport = $this->createStub(Passport::class);
        $passport->method('getUser')->willReturn($user);

        return new LoginSuccessEvent($authenticator, $passport, $token, $request, null, $firewall);
    }

    public function testOnLoginSuccessSetsLoginedAtForMainFirewall(): void
    {
        $user = $this->makeUser();
        $em   = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($user);
        $em->expects($this->once())->method('flush');

        $request = Request::create('/login');
        $event   = $this->makeLoginEvent($user, 'main', $request);

        $listener = $this->makeListener($em);
        $listener->onLoginSuccess($event);

        self::assertInstanceOf(DateTimeImmutable::class, $user->loginedAt);
    }

    public function testOnLoginSuccessEncryptsCfHeadersWhenPresent(): void
    {
        $user = $this->makeUser();
        $em   = $this->createStub(EntityManagerInterface::class);

        $request = Request::create('/login');
        $request->headers->set('CF-Connecting-IP', '203.0.113.42');
        $request->headers->set('CF-IPCountry', 'US');
        $request->headers->set('CF-Ray', 'abc123');

        $sodiumService = new SodiumEncryptionService();
        $listener      = $this->makeListener($em, $sodiumService, self::TEST_PUBLIC_KEY);
        $event         = $this->makeLoginEvent($user, 'main', $request);

        $listener->onLoginSuccess($event);

        self::assertArrayHasKey('cf', $user->profile);
        self::assertIsString($user->profile['cf']);

        // Verify the encrypted value is actually decryptable
        $decrypted = $sodiumService->decrypt($user->profile['cf'], self::TEST_PUBLIC_KEY, self::TEST_PRIVATE_KEY);
        $decoded   = json_decode($decrypted, true);

        self::assertArrayHasKey('cf-connecting-ip', $decoded);
        self::assertContains('203.0.113.42', $decoded['cf-connecting-ip']);
    }

    public function testOnLoginSuccessSkipsCfEncryptionWhenNoHeaders(): void
    {
        $user = $this->makeUser();
        $em   = $this->createStub(EntityManagerInterface::class);

        $request = Request::create('/login');
        // No CF-* headers
        $event   = $this->makeLoginEvent($user, 'main', $request);

        $listener = $this->makeListener($em);
        $listener->onLoginSuccess($event);

        self::assertArrayNotHasKey('cf', $user->profile);
    }

    public function testOnLoginSuccessSkipsCfEncryptionWhenKeyIsPlaceholder(): void
    {
        $user = $this->makeUser();
        $em   = $this->createStub(EntityManagerInterface::class);

        $request = Request::create('/login');
        $request->headers->set('CF-Connecting-IP', '203.0.113.42');
        $event = $this->makeLoginEvent($user, 'main', $request);

        $listener = $this->makeListener($em, null, 'changeme');
        $listener->onLoginSuccess($event);

        self::assertArrayNotHasKey('cf', $user->profile);
    }

    public function testOnLoginSuccessSkipsForNonMainFirewall(): void
    {
        $user = $this->makeUser();
        $em   = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $request = Request::create('/api/login');
        $event   = $this->makeLoginEvent($user, 'api', $request);

        $listener = $this->makeListener($em);
        $listener->onLoginSuccess($event);

        self::assertFalse(isset($user->loginedAt), 'loginedAt should remain uninitialized for non-main firewall');
        self::assertArrayNotHasKey('cf', $user->profile);
    }

    public function testOnLoginSuccessSkipsWhenUserIsNotUserEntity(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');

        $nonEntity    = new InMemoryUser('unknown@example.com', null, ['ROLE_USER']);
        $token        = new PostAuthenticationToken($nonEntity, 'main', ['ROLE_USER']);
        $authenticator = $this->createStub(AuthenticatorInterface::class);
        $passport     = $this->createStub(Passport::class);
        $event        = new LoginSuccessEvent($authenticator, $passport, $token, Request::create('/login'), null, 'main');

        $listener = $this->makeListener($em);
        $listener->onLoginSuccess($event);
    }

    public function testOnLogoutLogsWithoutException(): void
    {
        $user  = $this->makeUser();
        $token = new PostAuthenticationToken($user, 'main', $user->roles);

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->once())->method('log');

        $listener  = new UserSessionAuditListener(
            $logService,
            $this->createStub(EntityManagerInterface::class),
            new SodiumEncryptionService(),
            self::TEST_PUBLIC_KEY,
        );

        $event = new LogoutEvent(Request::create('/logout'), $token);
        $listener->onLogout($event);
    }

    public function testOnLogoutSkipsWhenNoToken(): void
    {
        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->never())->method('log');

        $listener = new UserSessionAuditListener(
            $logService,
            $this->createStub(EntityManagerInterface::class),
            new SodiumEncryptionService(),
            self::TEST_PUBLIC_KEY,
        );

        $event = new LogoutEvent(Request::create('/logout'), null);
        $listener->onLogout($event);
    }
}
