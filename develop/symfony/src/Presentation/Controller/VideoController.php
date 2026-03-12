<?php

namespace App\Presentation\Controller;

use App\Application\DTO\VideoListResponse;
use App\Application\Exception\QueryException;
use App\Application\Query\GetVideoListQuery;
use App\Application\Query\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
}
