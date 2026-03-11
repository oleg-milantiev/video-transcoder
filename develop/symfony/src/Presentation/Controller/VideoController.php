<?php

namespace App\Presentation\Controller;

use App\Application\DTO\VideoListResponse;
use App\Application\Exception\QueryException;
use App\Application\Query\GetVideoListQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/video')]
class VideoController extends AbstractController
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $messageBus,
    ) {
        $this->messageBus = $messageBus;
    }

    #[Route('/', name: 'video')]
    public function index(Request $request): Response
    {
        try {
            $query = new GetVideoListQuery($request);
        } catch (QueryException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        /** @var VideoListResponse $videoList */
        $videoList = $this->handle($query);

        // TODO use all video list data and paged api call in dataTable
        return new JsonResponse($videoList->items);
    }
}
