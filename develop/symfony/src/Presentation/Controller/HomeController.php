<?php

namespace App\Presentation\Controller;

use App\Infrastructure\Security\ApiTokenService;
use App\Infrastructure\Security\MercureTokenService;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
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

        return $this->render('home/index.html.twig', [
            'apiAccessToken' => $user ? $this->tokenService->createToken($user->id, $user->getUserIdentifier()) : null,
            'mercureHubUrl' => $this->mercureTokenService->publicHubUrl(),
            'mercureSubscriberToken' => $user ? $this->mercureTokenService->createSubscriberTokenForUser($user->id) : null,
            'mercureTopic' => $user ? $this->mercureTokenService->createUserTopic($user->id) : null,
            'userId' => $user?->id->toRfc4122(),
        ]);
    }
}
