<?php

namespace App\Presentation\Controller;

use App\Application\Exception\QueryException;
use App\Application\Query\GetVideoDetailsQuery;
use App\Application\QueryHandler\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class VideoController extends AbstractController
{
    public function __construct(
        private readonly QueryBus $queryBus,
    ) {
    }

    #[Route('/video/{uuid}', name: 'video_details', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function details(string $uuid): Response
    {
        try {
            return $this->render('video/details.html.twig', [
                'dto' => $this->queryBus->query(
                    new GetVideoDetailsQuery($uuid)
                ),
            ]);
        } catch (QueryException | \DomainException $e) {
            return new Response('Video not found', 404);
        }
    }
}
