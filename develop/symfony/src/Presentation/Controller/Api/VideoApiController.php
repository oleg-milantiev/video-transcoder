<?php

namespace App\Presentation\Controller\Api;

use App\Application\Exception\QueryException;
use App\Application\Exception\InvalidUuidException;
use App\Application\Exception\PresetNotFoundException;
use App\Application\Exception\TaskCreationFailedException;
use App\Application\Exception\TranscodeAccessDeniedException;
use App\Application\Exception\UserNotFoundException;
use App\Application\Exception\VideoNotFoundException;
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

#[Route('/api/video')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class VideoApiController extends AbstractController
{
    use ApiJsonResponseTrait;

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
            // TODO тут не $this->apiError?
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{id}', name: 'api_video_details', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['GET'])]
    public function details(string $id): Response
    {
        try {
            $dto = $this->queryBus->query(
                new GetVideoDetailsQuery($id)
            );

            return new JsonResponse($dto);
        } catch (QueryException $e) {
            $status = $e->getMessage() === 'Invalid UUID' ? 400 : 404;

            // TODO тут не $this->apiError?
            return new JsonResponse(['error' => $e->getMessage()], $status);
        } catch (\DomainException $e) {
            // TODO тут не $this->apiError?
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }
    }

    #[Route('/{id}/transcode/{presetId}', name: 'api_video_transcode', methods: ['POST'])]
    public function transcode(string $id, int $presetId): Response
    {
        try {
            $taskDto = $this->queryBus->query(
                new StartTranscodeQuery($id, $presetId, (int)$this->getUser()->id)
            );

            return $this->apiSuccess(['task' => (array) $taskDto]);
        } catch (InvalidUuidException $e) {
            return $this->apiError('INVALID_UUID', $e->getMessage(), 400);
        } catch (VideoNotFoundException $e) {
            return $this->apiError('VIDEO_NOT_FOUND', $e->getMessage(), 404);
        } catch (PresetNotFoundException $e) {
            return $this->apiError('PRESET_NOT_FOUND', $e->getMessage(), 404);
        } catch (UserNotFoundException $e) {
            return $this->apiError('USER_NOT_FOUND', $e->getMessage(), 404);
        } catch (TranscodeAccessDeniedException $e) {
            return $this->apiError('ACCESS_DENIED', $e->getMessage(), 403);
        } catch (TaskCreationFailedException $e) {
            return $this->apiError('TASK_CREATION_FAILED', $e->getMessage(), 500);
        } catch (QueryException $e) {
            return $this->apiError('QUERY_FAILED', $e->getMessage(), 400);
        } catch (\DomainException $e) {
            return $this->apiError('ACCESS_DENIED', $e->getMessage(), 403);
        } catch (\Throwable $e) {
            return $this->apiError('INTERNAL_ERROR', 'Failed to start transcode', 500);
        }
    }
}

