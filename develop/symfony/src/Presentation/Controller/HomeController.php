<?php

namespace App\Presentation\Controller;

use App\Domain\Shared\ValueObject\Uuid;
use App\Infrastructure\Security\ApiTokenService;
use App\Infrastructure\Security\MercureTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private readonly ApiTokenService $tokenService,
        private readonly MercureTokenService $mercureTokenService,
    ) {
    }

    /**
     * @throws \JsonException
     */
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        $user = $this->getUser();
        $userId = $user ? Uuid::fromString($user->id->toRfc4122()) : null;

        return $this->render('home/index.html.twig', [
            'apiAccessToken' => $user ? $this->tokenService->createToken($userId, $user->getUserIdentifier()) : null,
            'apiRefreshToken' => $user ? $this->tokenService->createRefreshToken($userId, $user->getUserIdentifier()) : null,
            'mercureHubUrl' => $this->mercureTokenService->publicHubUrl(),
            'mercureSubscriberToken' => $user ? $this->mercureTokenService->createSubscriberTokenForUser($userId) : null,
            'mercureTopic' => $user ? $this->mercureTokenService->createUserTopic($userId) : null,
            'userId' => $user ? $userId->toRfc4122() : null,
            'maxVideoSize' => $user?->tariff?->videoSize,
        ]);
    }
}
