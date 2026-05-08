<?php
declare(strict_types=1);

namespace App\Infrastructure\Security\EventListener;

use App\Application\Logging\LogServiceInterface;
use App\Domain\Shared\ValueObject\Uuid;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Security\SodiumEncryptionService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Throwable;

final readonly class UserSessionAuditListener
{
    public function __construct(
        private LogServiceInterface $logService,
        private EntityManagerInterface $entityManager,
        private SodiumEncryptionService $sodiumEncryptionService,
        #[Autowire('%env(CF_PUBLIC_KEY)%')]
        private string $cfPublicKey,
    ) {
    }

    #[AsEventListener(event: LoginSuccessEvent::class)]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof UserEntity || $user->id === null) {
            return;
        }

        $request = $event->getRequest();
        try {
            // skip api stateless auth log
            if ($event->getFirewallName() === 'main') {
                $user->loginedAt = new DateTimeImmutable();

                // Encrypt Cloudflare request headers and store in profile
                if ($this->cfPublicKey !== '' && $this->cfPublicKey !== 'changeme') {
                    $cfHeaders = array_filter(
                        $request->headers->all(),
                        static fn(string $name): bool => str_starts_with($name, 'cf-'),
                        ARRAY_FILTER_USE_KEY,
                    );

                    if ($cfHeaders !== []) {
                        $encrypted = $this->sodiumEncryptionService->encrypt(
                            json_encode($cfHeaders, JSON_THROW_ON_ERROR),
                            $this->cfPublicKey,
                        );
                        $profile = $user->profile;
                        $profile['cf'] = $encrypted;
                        $user->profile = $profile;
                    }
                }

                $this->entityManager->persist($user);
                $this->entityManager->flush();
                $this->logService->log(
                    'user',
                    'login',
                    Uuid::fromString($user->id->toRfc4122()),
                    LogLevel::INFO,
                    'User signed in',
                    [
                        'firewall' => $event->getFirewallName(),
                        'route' => (string)$request->attributes->get('_route', ''),
                        'ip' => $request->getClientIp(),
                        'userAgent' => (string)$request->headers->get('User-Agent', ''),
                    ]
                );
            }
        } catch (Throwable) {
            // Audit logging should not block authentication flow.
        }
    }

    #[AsEventListener(event: LogoutEvent::class)]
    public function onLogout(LogoutEvent $event): void
    {
        $user = $event->getToken()?->getUser();
        if (!$user instanceof UserEntity || $user->id === null) {
            return;
        }

        $request = $event->getRequest();
        try {
            $this->logService->log(
                'user',
                'logout',
                Uuid::fromString($user->id->toRfc4122()),
                LogLevel::INFO,
                'User signed out',
                [
                    'route' => (string)$request->attributes->get('_route', ''),
                    'ip' => $request->getClientIp(),
                    'userAgent' => (string)$request->headers->get('User-Agent', ''),
                ]
            );
        } catch (Throwable) {
            // Audit logging should not block logout flow.
        }
    }
}
