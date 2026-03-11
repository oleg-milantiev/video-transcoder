<?php

namespace App\Presentation\Controller;

use App\Application\DTO\VideoListResponse;
use App\Application\Exception\QueryException;
use App\Application\Query\GetVideoListQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[Route('/video')]
class VideoController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/', name: 'video')]
    public function index(Request $request): Response
    {
        try {
            $query = new GetVideoListQuery($request);
        } catch (QueryException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        // TODO simplify!
        /** @var Envelope $envelope */
        $envelope = $this->messageBus->dispatch($query);
        $handledStamp = $envelope->last(HandledStamp::class);
        if (!$handledStamp) {
            throw new \RuntimeException('No handler processed this query');
        }
        /** @var VideoListResponse $result */
        $result = $handledStamp->getResult();

        // TODO use all video list data and paged api call in dataTable
        return new JsonResponse($result->items);
    }
}
