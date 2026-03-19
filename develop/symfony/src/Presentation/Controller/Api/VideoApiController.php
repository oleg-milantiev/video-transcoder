<?php

namespace App\Presentation\Controller\Api;

use App\Application\Exception\QueryException;
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

#[Route('/api/video')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class VideoApiController extends AbstractController
{
    public function __construct(
        private readonly QueryBus $queryBus,
    ) {
    }

    #[Route('/', name: 'api_video_list', methods: ['GET'])]
    public function index(Request $request): Response
    {
        try {
            /** @var VideoListResponse $videoListResponse */
            $videoListResponse = $this->queryBus->query(
                new GetVideoListQuery($request)
            );

            return new JsonResponse($videoListResponse);
        } catch (QueryException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{id}/transcode/{presetId}', name: 'api_video_transcode', methods: ['POST'])]
    public function transcode(string $id, int $presetId): Response
    {
        try {
            $taskDto = $this->queryBus->query(
                new StartTranscodeQuery($id, $presetId, (int)$this->getUser()->id)
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

