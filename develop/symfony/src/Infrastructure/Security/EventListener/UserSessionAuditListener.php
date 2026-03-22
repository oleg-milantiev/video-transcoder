<?php

namespace App\Infrastructure\Security\EventListener;

use App\Application\Logging\LogServiceInterface;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final readonly class UserSessionAuditListener
{
    public function __construct(private LogServiceInterface $logService)
    {
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
            $this->logService->log('user', $user->id, LogLevel::INFO, 'User signed in', [
                'firewall' => $event->getFirewallName(),
                'route' => (string) $request->attributes->get('_route', ''),
                'ip' => $request->getClientIp(),
                'userAgent' => (string) $request->headers->get('User-Agent', ''),
            ]);
        } catch (\Throwable) {
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
            $this->logService->log('user', $user->id, LogLevel::INFO, 'User signed out', [
                'route' => (string) $request->attributes->get('_route', ''),
                'ip' => $request->getClientIp(),
                'userAgent' => (string) $request->headers->get('User-Agent', ''),
            ]);
        } catch (\Throwable) {
            // Audit logging should not block logout flow.
        }
    }
}
