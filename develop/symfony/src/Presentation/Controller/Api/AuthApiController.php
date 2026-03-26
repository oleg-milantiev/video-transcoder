<?php

namespace App\Presentation\Controller\Api;

use App\Application\Logging\LogServiceInterface;
use App\Domain\Shared\ValueObject\Uuid;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Security\ApiTokenService;
use Psr\Log\LogLevel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

#[Route('/api/auth')]
final class AuthApiController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'security.user.provider.concrete.app_user_provider')]
        private readonly UserProviderInterface $userProvider,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ApiTokenService $tokenService,
        private readonly LogServiceInterface $logService,
    ) {
    }

    /**
     * @throws \JsonException
     */
    #[Route('/token', name: 'api_auth_token', methods: ['POST'])]
    public function token(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Invalid JSON payload.'], Response::HTTP_BAD_REQUEST);
        }

        $email = trim((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if ($email === '' || $password === '') {
            return new JsonResponse(['error' => 'Email and password are required.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $this->userProvider->loadUserByIdentifier($email);
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user instanceof PasswordAuthenticatedUserInterface || !$user instanceof UserEntity) {
            return new JsonResponse(['error' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            return new JsonResponse(['error' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->id === null) {
            return new JsonResponse(['error' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);
        }

        $this->logService->log('user', Uuid::fromString($user->id->toRfc4122()), LogLevel::INFO, 'User signed in via API token', [
            'email' => $user->getUserIdentifier(),
            'ip' => $request->getClientIp(),
            'route' => (string) $request->attributes->get('_route', 'api_auth_token'),
        ]);

        return new JsonResponse([
            'tokenType' => 'Bearer',
            'accessToken' => $this->tokenService->createToken($user->id, $user->getUserIdentifier()),
            'expiresIn' => $this->tokenService->ttlSeconds(),
        ]);
    }
}


