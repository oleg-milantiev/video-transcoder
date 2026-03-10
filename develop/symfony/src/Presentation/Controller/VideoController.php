<?php

namespace App\Presentation\Controller;

use App\Application\DTO\VideoListResponse;
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
        $query = new GetVideoListQuery(
            page: $request->query->getInt('page', 1),
            // TODO use paged api calls in dataTable
            limit: $request->query->getInt('limit', 10000),
        );

        /** @var VideoListResponse $videoList */
        $videoList = $this->handle($query);

        // TODO use all video list data
        return new JsonResponse($videoList->items);
    }
}
