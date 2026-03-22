<?php

namespace App\Presentation\Controller;

use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Security\ApiTokenService;
use App\Infrastructure\Security\MercureTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class VideoController extends AbstractController
{
    public function __construct(
        private readonly ApiTokenService $tokenService,
        private readonly MercureTokenService $mercureTokenService,
    ) {
    }

    #[Route('/video/{uuid}', name: 'video_details', requirements: ['uuid' => '[0-9a-fA-F-]{36}'], methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function details(string $uuid): Response
    {
        $user = $this->getUser();

        return $this->render('video/details.html.twig', [
            'apiAccessToken' => $user ? $this->tokenService->createToken($user->id, $user->getUserIdentifier()) : null,
            'mercureHubUrl' => $this->mercureTokenService->publicHubUrl(),
            'mercureSubscriberToken' => $user ? $this->mercureTokenService->createSubscriberTokenForUser($user->id) : null,
            'mercureTopic' => $user ? $this->mercureTokenService->createUserTopic($user->id) : null,
            'userId' => $user?->id->toRfc4122(),
            'uuid' => $uuid,
        ]);
    }
}
