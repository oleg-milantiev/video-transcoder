<?php

namespace App\Presentation\Controller;

use App\Application\Exception\QueryException;
use App\Application\Query\GetVideoDetailsQuery;
use App\Application\Query\StartTranscodeQuery;
use App\Application\Query\GetVideoListQuery;
use App\Application\Query\QueryBus;
use App\Application\Response\VideoListResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// TODO не очень хорошо перепутался JSON в index и шаблон в details.
// Унифицировать, как перейду на Vue
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
    public function details(string $uuid): Response
    {
        try {
            $dto = $this->queryBus->query(
                new GetVideoDetailsQuery($uuid)
            );
            return $this->render('video/details.html.twig', [
                'dto' => $dto,
            ]);
        } catch (QueryException | \DomainException $e) {
            return new Response('Video not found', 404);
        }
    }

    #[Route('/{id}/transcode/{presetId}', name: 'video_transcode', methods: ['POST'])]
    public function transcode(string $id, int $presetId): Response
    {
        try {
            // TODO аттрибутами метода обязательно авторизованного
            $user = $this->getUser();
            if (!$user) {
                return new JsonResponse(['error' => 'Unauthorized'], 401);
            }

            $taskDto = $this->queryBus->query(new StartTranscodeQuery($id, $presetId, (int)$user->getId()));

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
