<?php

namespace App\Presentation\Controller;

use App\Application\Exception\QueryException;
use App\Application\Query\GetVideoDetailsQuery;
use App\Application\Query\GetVideoListQuery;
use App\Application\Query\StartTranscodeQuery;
use App\Application\QueryHandler\QueryBus;
use App\Application\Response\VideoListResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/video')]
class VideoController extends AbstractController
{
    public function __construct(
        private readonly QueryBus $queryBus,
    ) {
    }

    #[Route('/', name: 'video')]
    public function index(Request $request): Response
    {
        try {
            /** @var VideoListResponse $videoListResponse */
            $videoListResponse = $this->queryBus->query(
                new GetVideoListQuery($request)
            );

            // TODO use all tasks list data and paged api call in dataTable
            return new JsonResponse($videoListResponse->items);
        } catch (QueryException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{uuid}', name: 'video_details', methods: ['GET'])]
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

    #[Route('/{id}/transcode/{presetId}', name: 'video_transcode', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function transcode(string $id, int $presetId): Response
    {
        try {
            $taskDto = $this->queryBus->query(
                new StartTranscodeQuery($id, $presetId, (int)$this->getUser()->getId())
            );

            return new JsonResponse($taskDto);
        } catch (QueryException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\DomainException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Failed to start transcode'], 500);
        }
    }
}
