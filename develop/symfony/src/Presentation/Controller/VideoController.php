<?php

namespace App\Presentation\Controller;

use App\Application\DTO\VideoDetailsDTO;
use App\Application\DTO\VideoListResponse;
use App\Application\Exception\QueryException;
use App\Application\Query\GetVideoDetailsQuery;
use App\Application\Query\GetVideoListQuery;
use App\Application\Query\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Uid\UuidV4;

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
            $uuidObj = UuidV4::fromString($uuid);
        } catch (\Throwable $e) {
            throw new InvalidParameterException('Invalid UUID');
        }
        try {
            /** @var VideoDetailsDTO $video */
            $video = $this->queryBus->query(
                new GetVideoDetailsQuery($uuidObj)
            );
            return $this->render('video/details.html.twig', [
                'video' => $video,
            ]);
        } catch (QueryException | \DomainException $e) {
            return new Response('Video not found', 404);
        }
    }
}
