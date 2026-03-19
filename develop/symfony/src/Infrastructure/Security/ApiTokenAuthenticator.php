<?php

namespace App\Infrastructure\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

final class ApiTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly ApiTokenService $tokenService,
        #[Autowire(service: 'security.user.provider.concrete.app_user_provider')]
        private readonly UserProviderInterface $userProvider,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return false;
        }

        if ($request->getPathInfo() === '/api/auth/token') {
            return false;
        }

        return true;
    }

    public function authenticate(Request $request): Passport
    {
        $header = (string) $request->headers->get('Authorization', '');
        if (!preg_match('/^Bearer\s+(.+)$/', $header, $matches)) {
            throw new CustomUserMessageAuthenticationException('Missing Bearer token.');
        }

        $rawToken = trim($matches[1]);

        try {
            $claims = $this->tokenService->parseToken($rawToken);
        } catch (\Throwable $e) {
            throw new CustomUserMessageAuthenticationException('Invalid or expired token.');
        }

        $identifier = $claims['identifier'];

        return new SelfValidatingPassport(
            new UserBadge(
                $identifier,
                fn (string $userIdentifier) => $this->userProvider->loadUserByIdentifier($userIdentifier)
            )
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'error' => $exception->getMessageKey(),
        ], Response::HTTP_UNAUTHORIZED);
    }
}

