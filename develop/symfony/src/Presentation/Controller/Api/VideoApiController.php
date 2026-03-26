<?php

namespace App\Presentation\Controller\Api;

use App\Application\Exception\InvalidUuidException;
use App\Application\Exception\QueryException;
use App\Application\Exception\PresetNotFoundException;
use App\Application\Exception\TaskCreationFailedException;
use App\Application\Exception\TranscodeAccessDeniedException;
use App\Application\Exception\UserNotFoundException;
use App\Application\Exception\VideoNotFoundException;
use App\Application\Query\DeleteVideoQuery;
use App\Application\Query\GetVideoDetailsQuery;
use App\Application\Query\GetVideoListQuery;
use App\Application\Query\StartTranscodeQuery;
use App\Application\QueryHandler\QueryBus;
use App\Application\Response\VideoListResponse;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Exception\VideoAlreadyDeleted;
use App\Domain\Video\Exception\VideoHasTranscodingTasks;
use Psr\Log\LoggerInterface;
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
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/', name: 'api_video_list', methods: ['GET'])]
    public function index(Request $request): Response
    {
        try {
            /** @var VideoListResponse $videoListResponse */
            $videoListResponse = $this->queryBus->query(
                new GetVideoListQuery($request, Uuid::fromString($this->getUser()->id->toRfc4122()))
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
    public function transcode(string $id, string $presetId): Response
    {
        try {
            $taskDto = $this->queryBus->query(
                new StartTranscodeQuery($id, $presetId, $this->getUser()->id->toRfc4122())
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
            $this->logger->critical('Failed to start transcode', ['exception' => $e]);
            return $this->apiError('INTERNAL_ERROR', 'Failed to start transcode', 500);
        }
    }

    #[Route('/{id}', name: 'api_video_patch', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['PATCH'])]
    public function patch(string $id, Request $request): Response
    {
        $payload = json_decode($request->getContent() ?: '{}', true);
        $title = isset($payload['title']) ? (string)$payload['title'] : null;

        if ($title === null) {
            return $this->apiError('INVALID_PAYLOAD', 'Missing title', 400);
        }

        try {
            $this->queryBus->query(new \App\Application\Query\PatchVideoQuery($id, $title, $this->getUser()->id->toRfc4122()));

            return $this->apiSuccess([]);
        } catch (InvalidUuidException $e) {
            return $this->apiError('INVALID_VIDEO_ID', $e->getMessage(), 400);
        } catch (VideoNotFoundException $e) {
            return $this->apiError('VIDEO_NOT_FOUND', $e->getMessage(), 404);
        } catch (\DomainException $e) {
            return $this->apiError('ACCESS_DENIED', $e->getMessage(), 403);
        } catch (\Throwable $e) {
            $this->logger->critical('Failed to patch video', ['exception' => $e]);
            return $this->apiError('INTERNAL_ERROR', 'Failed to patch video', 500);
        }
    }

    #[Route('/{id}/delete', name: 'api_video_delete', methods: ['POST'])]
    public function delete(string $id): Response
    {
        try {
            $query = new DeleteVideoQuery($id, $this->getUser()->id->toRfc4122());
            $this->queryBus->query($query);

            return $this->apiSuccess([
                'video' => [
                    'id' => $query->videoId->toRfc4122(),
                    'deleted' => true,
                ],
            ]);
        } catch (InvalidUuidException $e) {
            return $this->apiError('INVALID_VIDEO_ID', $e->getMessage(), 400);
        } catch (TranscodeAccessDeniedException $e) {
            return $this->apiError('ACCESS_DENIED', $e->getMessage(), 403);
        } catch (VideoNotFoundException $e) {
            return $this->apiError('VIDEO_NOT_FOUND', $e->getMessage(), 404);
        } catch (VideoAlreadyDeleted $e) {
            return $this->apiError('VIDEO_ALREADY_DELETED', $e->getMessage(), 409);
        } catch (VideoHasTranscodingTasks $e) {
            return $this->apiError('VIDEO_HAS_TRANSCODING_TASKS', $e->getMessage(), 409);
        } catch (\DomainException $e) {
            return $this->apiError('DELETE_NOT_ALLOWED', $e->getMessage(), 409);
        } catch (\Throwable $e) {
            $this->logger->critical('Failed to delete video', ['exception' => $e]);
            return $this->apiError('INTERNAL_ERROR', 'Failed to delete video', 500);
        }
    }
}
