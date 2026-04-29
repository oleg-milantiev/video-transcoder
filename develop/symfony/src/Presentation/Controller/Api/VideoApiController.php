<?php
declare(strict_types=1);

namespace App\Presentation\Controller\Api;

use App\Application\Command\Video\CreateVideo;
use App\Application\Exception\HeightExceedsTariffException;
use App\Application\Exception\InvalidUploadUrlException;
use App\Application\Exception\InvalidUuidException;
use App\Application\Exception\PresetHeightNotAvailableException;
use App\Application\Exception\PresetNotFoundException;
use App\Application\Exception\TaskCreationFailedException;
use App\Application\Exception\TranscodeAccessDeniedException;
use App\Application\Exception\UserNotFoundException;
use App\Application\Exception\VideoAccessDeniedException;
use App\Application\Exception\VideoNotFoundException;
use App\Application\Logging\LogServiceInterface;
use App\Application\Query\DeleteVideoQuery;
use App\Application\Query\GetVideoDetailsQuery;
use App\Application\Query\GetVideoListQuery;
use App\Application\Query\PatchVideoQuery;
use App\Application\Query\StartTranscodeQuery;
use App\Application\QueryHandler\QueryBus;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\User\Exception\TariffNotFound;
use App\Domain\Video\Exception\VideoAlreadyDeleted;
use App\Domain\Video\Exception\VideoHasTranscodingTasks;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use DomainException;
use Psr\Log\LogLevel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

#[Route('/api/video')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class VideoApiController extends AbstractController
{
    use ApiJsonResponseTrait;

    public function __construct(
        private readonly QueryBus $queryBus,
        private readonly LogServiceInterface $logService,
        private readonly VideoRepositoryInterface $videoRepository,
        #[Autowire(service: 'messenger.bus.command')]
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    /**
     * Accept a remote video URL for download-and-transcode.
     *
     * POST /api/video/upload
     * Body: { "url": "https://example.com/video.mp4" }
     *
     * The controller only validates the URL is present and well-formed, then
     * enqueues a CreateVideo command.  All downloading, size checks and
     * quota enforcement happen asynchronously inside CreateVideoHandler.
     * Use returned session id for video.id find via GET /api/video/session/{uuid}
     */
    #[IsGranted('ROLE_API')]
    #[Route('/upload', name: 'api_video_upload', methods: ['POST'])]
    public function upload(Request $request): Response
    {
        $url = trim((string)($request->request->get('url') ?? $request->toArray()['url'] ?? ''));

        if ($url === '') {
            return $this->apiError('MISSING_URL', 'Parameter "url" is required.', 400);
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->apiError('INVALID_URL', 'The provided URL is not valid.', 422);
        }

        try {
            $userId = Uuid::fromString($this->getUser()->id->toRfc4122());
            $session = Uuid::generate()->toRfc4122();

            $this->commandBus->dispatch(new CreateVideo(null, $userId, $url, $session));

            return $this->apiSuccess(['session' => $session], 202);
        } catch (InvalidUploadUrlException $e) {
            return $this->apiError('INVALID_URL', $e->getMessage(), 422);
        } catch (Throwable $e) {
            $this->logService->log('video', 'upload', null, LogLevel::ERROR, 'URL upload dispatch failed', [
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            return $this->apiError('INTERNAL_ERROR', 'Failed to accept upload from URL.', 500);
        }
    }

    #[IsGranted('ROLE_API')]
    #[Route('/session/{session}', name: 'api_video_session', requirements: ['session' => '[0-9a-fA-F-]{36}'], methods: ['GET'])]
    public function session(string $session): Response
    {
        try {
            $userId = Uuid::fromString($this->getUser()->id->toRfc4122());
            $videoId = $this->videoRepository->findIdBySession($session, $userId);

            if ($videoId === null) {
                return $this->apiSuccess(null, Response::HTTP_NO_CONTENT);
            }

            return $this->apiSuccess(['id' => $videoId->toRfc4122()]);
        } catch (Throwable $e) {
            $this->logService->log('video', 'session', null, LogLevel::ERROR, 'Session lookup failed', [
                'session' => $session,
                'message' => $e->getMessage(),
            ]);

            return $this->apiError('INTERNAL_ERROR', 'Failed to look up session.', 500);
        }
    }

    #[Route('/', name: 'api_video_list', methods: ['GET'])]
    public function index(Request $request): Response
    {
        try {
            return $this->apiSuccess(
                (array)
                $this->queryBus->query(
                    new GetVideoListQuery($request, Uuid::fromString($this->getUser()->id->toRfc4122()))
                )
            );
        } catch (Throwable $e) {
            $this->logService->log('video', 'index', null, LogLevel::CRITICAL, 'Fail', [
                'message' => $e->getMessage(),
            ]);

            return $this->apiError('INTERNAL_ERROR', 'Failed to list videos', 500);
        }
    }

    #[Route('/{id}', name: 'api_video_details', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['GET'])]
    public function details(string $id): Response
    {
        try {
            return $this->apiSuccess(
                (array)
                $this->queryBus->query(
                    new GetVideoDetailsQuery($id)
                )
            );
        } catch (InvalidUuidException $e) {
            return $this->apiError('INVALID_UUID', $e->getMessage(), 400);
        } catch (VideoNotFoundException $e) {
            return $this->apiError('VIDEO_NOT_FOUND', $e->getMessage(), 404);
        } catch (TariffNotFound $e) {
            return $this->apiError('TARIFF_NOT_FOUND', $e->getMessage(), 404);
        } catch (VideoAccessDeniedException $e) {
            return $this->apiError('ACCESS_DENIED', $e->getMessage(), 403);
        } catch (Throwable $e) {
            $this->logService->log('video', 'details', Uuid::fromStringNullable($id), LogLevel::CRITICAL, 'Fail', [
                'id' => $id,
                'message' => $e->getMessage(),
            ]);

            return $this->apiError('INTERNAL_ERROR', 'Failed to get video details', 500);
        }
    }

    #[Route('/{id}/transcode/{presetId}/{height}', name: 'api_video_transcode', requirements: [
        'id' => '[0-9a-fA-F-]{36}',
        'presetId' => '[0-9a-fA-F-]{36}',
        'height' => '\d+',
    ], methods: ['POST'])]
    public function transcode(string $id, string $presetId, int $height): Response
    {
        try {
            return $this->apiSuccess(
                (array)
                $this->queryBus->query(
                    new StartTranscodeQuery($id, $presetId, $this->getUser()->id->toRfc4122(), $height)
                )
            );
        } catch (InvalidUuidException $e) {
            return $this->apiError('INVALID_UUID', $e->getMessage(), 400);
        } catch (VideoNotFoundException $e) {
            return $this->apiError('VIDEO_NOT_FOUND', $e->getMessage(), 404);
        } catch (PresetNotFoundException $e) {
            return $this->apiError('PRESET_NOT_FOUND', $e->getMessage(), 404);
        } catch (UserNotFoundException $e) {
            return $this->apiError('USER_NOT_FOUND', $e->getMessage(), 404);
        } catch (VideoAccessDeniedException $e) {
            return $this->apiError('ACCESS_DENIED', $e->getMessage(), 403);
        } catch (HeightExceedsTariffException $e) {
            return $this->apiError('HEIGHT_EXCEEDS_TARIFF', $e->getMessage(), 403);
        } catch (PresetHeightNotAvailableException $e) {
            return $this->apiError('HEIGHT_NOT_IN_PRESET', $e->getMessage(), 422);
        } catch (TaskCreationFailedException $e) {
            return $this->apiError('TASK_CREATION_FAILED', $e->getMessage(), 500);
        } catch (Throwable $e) {
            $this->logService->log('video', 'transcode', Uuid::fromStringNullable($id), LogLevel::CRITICAL, 'Fail', [
                'id' => $id,
                'presetId' => $presetId,
                'message' => $e->getMessage(),
            ]);

            return $this->apiError('INTERNAL_ERROR', 'Failed to start transcode', 500);
        }
    }

    #[Route('/{id}', name: 'api_video_patch', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['PATCH'])]
    public function patch(string $id, Request $request): Response
    {
        try {
            return $this->apiSuccess(
                (array)
                $this->queryBus->query(
                    new PatchVideoQuery($id, $request, $this->getUser()->id->toRfc4122())
                )
            );
        } catch (InvalidUuidException $e) {
            return $this->apiError('INVALID_VIDEO_ID', $e->getMessage(), 400);
        } catch (VideoNotFoundException $e) {
            return $this->apiError('VIDEO_NOT_FOUND', $e->getMessage(), 404);
        } catch (DomainException $e) {
            return $this->apiError('ACCESS_DENIED', $e->getMessage(), 403);
        } catch (Throwable $e) {
            $this->logService->log('video', 'patch', Uuid::fromStringNullable($id), LogLevel::CRITICAL, 'Fail', [
                'id' => $id,
                'message' => $e->getMessage(),
            ]);

            return $this->apiError('INTERNAL_ERROR', 'Failed to patch video', 500);
        }
    }

    #[Route('/{id}', name: 'api_video_delete', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['DELETE'])]
    public function delete(string $id): Response
    {
        try {
            $this->queryBus->query(
                new DeleteVideoQuery($id, $this->getUser()->id->toRfc4122())
            );

            return $this->apiSuccess(null, 204);
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
        } catch (DomainException $e) {
            return $this->apiError('DELETE_NOT_ALLOWED', $e->getMessage(), 409);
        } catch (Throwable $e) {
            $this->logService->log('video', 'delete', Uuid::fromStringNullable($id), LogLevel::CRITICAL, 'Fail', [
                'id' => $id,
                'message' => $e->getMessage(),
            ]);

            return $this->apiError('INTERNAL_ERROR', 'Failed to delete video', 500);
        }
    }
}
