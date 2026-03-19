<?php

namespace App\Presentation\Controller;

use App\Application\Exception\QueryException;
use App\Application\Query\GetVideoDetailsQuery;
use App\Application\QueryHandler\QueryBus;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Security\ApiTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class VideoController extends AbstractController
{
    public function __construct(
        private readonly QueryBus $queryBus,
        private readonly ApiTokenService $tokenService,
    ) {
    }

    #[Route('/video/{uuid}', name: 'video_details', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function details(string $uuid): Response
    {
        try {
            $user = $this->getUser();
            $apiAccessToken = null;

            if ($user instanceof UserEntity && $user->id !== null) {
                $apiAccessToken = $this->tokenService->createToken($user->id, $user->getUserIdentifier());
            }

            return $this->render('video/details.html.twig', [
                'dto' => $this->queryBus->query(
                    new GetVideoDetailsQuery($uuid)
                ),
                'apiAccessToken' => $apiAccessToken,
            ]);
        } catch (QueryException | \DomainException $e) {
            return new Response('Video not found', 404);
        }
    }
}
