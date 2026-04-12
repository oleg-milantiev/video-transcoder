<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller\Api;

use App\Application\Exception\QueryException;
use App\Application\Exception\TranscodeAccessDeniedException;
use App\Application\Exception\VideoAccessDeniedException;
use App\Application\Exception\VideoNotFoundException;
use App\Application\Query\DeleteVideoQuery;
use App\Application\Query\GetVideoDetailsQuery;
use App\Application\Query\GetVideoListQuery;
use App\Application\Query\StartTranscodeQuery;
use App\Application\QueryHandler\QueryBus;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Exception\VideoHasTranscodingTasks;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

final class VideoApiControllerTest extends ApiWebTestCase
{
    /**
     * @throws \JsonException
     */
    public function testListReturnsPaginatedPayload(): void
    {
        $client = $this->createBearerAuthenticatedClient();

        $items = [
            ['uuid' => '11111111-1111-4111-8111-111111111111', 'title' => 'Video A'],
            ['uuid' => '22222222-2222-4222-8222-222222222222', 'title' => 'Video B'],
        ];

        $listPayload = [
            'items' => $items,
            'total' => 17,
            'page' => 2,
            'limit' => 5,
            'totalPages' => 4,
        ];

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->with($this->callback(function (object $query): bool {
                return $query instanceof GetVideoListQuery
                    && $query->page === 2
                    && $query->limit === 5;
            }))
            ->willReturn((object) $listPayload);

        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('GET', '/api/video/?page=2&limit=5');

        self::assertResponseStatusCodeSame(200);
        self::assertSame($listPayload, $this->decodeJson($client->getResponse()->getContent()));
    }

    /**
     * @throws \JsonException
     */
    public function testListReturnsBadRequestOnQueryException(): void
    {
        $client = $this->createBearerAuthenticatedClient();

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->willThrowException(new QueryException('List failed'));
        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('GET', '/api/video/');

        self::assertResponseStatusCodeSame(500);
        self::assertSame([
            'error' => [
                'code' => 'INTERNAL_ERROR',
                'message' => 'Failed to list videos',
                'details' => [],
            ],
        ], $this->decodeJson($client->getResponse()->getContent()));
    }

    /**
     * @throws \JsonException
     */
    public function testDetailsReturnsPayload(): void
    {
        $client = $this->createBearerAuthenticatedClient();

        $payload = [
            'id' => '11111111-1111-4111-8111-111111111111',
            'title' => 'Video A',
            'extension' => 'mp4',
            'status' => 'READY',
            'createdAt' => '2026-03-19 09:00',
            'updatedAt' => null,
            'userId' => '11111111-1111-4111-8111-111111111111',
            'meta' => ['duration' => 12],
            'poster' => null,
            'presetsWithTasks' => [],
        ];

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->with($this->callback(function (object $query): bool {
                return $query instanceof GetVideoDetailsQuery
                    && $query->uuid->toRfc4122() === '11111111-1111-4111-8111-111111111111';
            }))
            ->willReturn((object) $payload);

        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('GET', '/api/video/11111111-1111-4111-8111-111111111111');

        self::assertResponseStatusCodeSame(200);
        self::assertSame($payload, $this->decodeJson($client->getResponse()->getContent()));
    }

    /**
     * @throws \JsonException
     */
    public function testDetailsReturnsNotFoundOnQueryException(): void
    {
        $client = $this->createBearerAuthenticatedClient();

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->willThrowException(new VideoNotFoundException('Video not found'));
        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('GET', '/api/video/11111111-1111-4111-8111-111111111111');

        self::assertResponseStatusCodeSame(404);
        self::assertSame([
            'error' => [
                'code' => 'VIDEO_NOT_FOUND',
                'message' => 'Video not found',
                'details' => [],
            ],
        ], $this->decodeJson($client->getResponse()->getContent()));
    }

    /**
     * @throws \JsonException
     */
    public function testTranscodeReturnsTaskPayload(): void
    {
        $client = $this->createBearerAuthenticatedClient(
            userId: SymfonyUuid::fromString('00000000-0000-4000-8000-000000000042'),
            roles: ['ROLE_ADMIN'],
        );

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->with($this->callback(function (object $query): bool {
                return $query instanceof StartTranscodeQuery
                    && $query->uuid->toRfc4122() === '11111111-1111-4111-8111-111111111111'
                    && $query->presetId->toRfc4122() === '77777777-7777-4777-8777-777777777777'
                    && $query->userId->toRfc4122() === '00000000-0000-4000-8000-000000000042';
            }))
            ->willReturn(['taskId' => '15151515-1515-4515-8515-151515151515', 'status' => 'PENDING']);

        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('POST', '/api/video/11111111-1111-4111-8111-111111111111/transcode/77777777-7777-4777-8777-777777777777');

        self::assertResponseStatusCodeSame(200);
        self::assertSame([
            'taskId' => '15151515-1515-4515-8515-151515151515',
            'status' => 'PENDING',
        ], $this->decodeJson($client->getResponse()->getContent()));
    }

    /**
     * @throws \JsonException
     */
    public function testTranscodeReturnsBadRequestForInvalidUuid(): void
    {
        $client = $this->createBearerAuthenticatedClient(roles: ['ROLE_ADMIN']);

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->never())->method('query');
        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('POST', '/api/video/not-a-uuid/transcode/77777777-7777-4777-8777-777777777777');

        self::assertResponseStatusCodeSame(400);
        self::assertSame([
            'error' => [
                'code' => 'INVALID_UUID',
                'message' => 'Invalid UUID',
                'details' => [],
            ],
        ], $this->decodeJson($client->getResponse()->getContent()));
    }

    /**
     * @throws \JsonException
     */
    public function testTranscodeReturnsForbidden(): void
    {
        $client = $this->createBearerAuthenticatedClient();

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->willThrowException(new VideoAccessDeniedException('Access denied'));
        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('POST', '/api/video/11111111-1111-4111-8111-111111111111/transcode/77777777-7777-4777-8777-777777777777');

        self::assertResponseStatusCodeSame(403);
        self::assertSame([
            'error' => [
                'code' => 'ACCESS_DENIED',
                'message' => 'Access denied',
                'details' => [],
            ],
        ], $this->decodeJson($client->getResponse()->getContent()));
    }

    /**
     * @throws \JsonException
     */
    public function testTranscodeReturnsServerErrorOnUnexpectedException(): void
    {
        $client = $this->createBearerAuthenticatedClient();

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->willThrowException(new \RuntimeException('boom'));
        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('POST', '/api/video/11111111-1111-4111-8111-111111111111/transcode/77777777-7777-4777-8777-777777777777');

        self::assertResponseStatusCodeSame(500);
        self::assertSame([
            'error' => [
                'code' => 'INTERNAL_ERROR',
                'message' => 'Failed to start transcode',
                'details' => [],
            ],
        ], $this->decodeJson($client->getResponse()->getContent()));
    }

    // ── /delete additional error branches ────────────────────────────────────

    public function testDeleteReturnsNotFoundWhenVideoDoesNotExist(): void
    {
        $client = $this->createBearerAuthenticatedClient();
        $videoId = Uuid::fromString('11111111-1111-4111-8111-111111111111');

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->willThrowException(new VideoNotFoundException('Video not found'));
        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('DELETE', '/api/video/' . $videoId->toRfc4122());

        self::assertResponseStatusCodeSame(404);
        self::assertSame([
            'error' => ['code' => 'VIDEO_NOT_FOUND', 'message' => 'Video not found', 'details' => []],
        ], $this->decodeJson($client->getResponse()->getContent()));
    }

    public function testDeleteReturnsConflictWhenVideoAlreadyDeleted(): void
    {
        $client = $this->createBearerAuthenticatedClient();
        $videoId = Uuid::fromString('11111111-1111-4111-8111-111111111111');

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->willThrowException(\App\Domain\Video\Exception\VideoAlreadyDeleted::forVideo());
        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('DELETE', '/api/video/' . $videoId->toRfc4122());

        self::assertResponseStatusCodeSame(409);
        self::assertSame([
            'error' => ['code' => 'VIDEO_ALREADY_DELETED', 'message' => 'Video is already deleted.', 'details' => []],
        ], $this->decodeJson($client->getResponse()->getContent()));
    }

    public function testDeleteReturnsConflictOnDomainException(): void
    {
        $client = $this->createBearerAuthenticatedClient();
        $videoId = Uuid::fromString('11111111-1111-4111-8111-111111111111');

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->willThrowException(new \DomainException('Custom domain constraint'));
        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('DELETE', '/api/video/' . $videoId->toRfc4122());

        self::assertResponseStatusCodeSame(409);
        self::assertSame([
            'error' => ['code' => 'DELETE_NOT_ALLOWED', 'message' => 'Custom domain constraint', 'details' => []],
        ], $this->decodeJson($client->getResponse()->getContent()));
    }

    public function testDeleteReturnsServerErrorOnUnexpectedException(): void
    {
        $client = $this->createBearerAuthenticatedClient();
        $videoId = Uuid::fromString('11111111-1111-4111-8111-111111111111');

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->willThrowException(new \RuntimeException('boom'));
        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('DELETE', '/api/video/' . $videoId->toRfc4122());

        self::assertResponseStatusCodeSame(500);
        self::assertSame([
            'error' => ['code' => 'INTERNAL_ERROR', 'message' => 'Failed to delete video', 'details' => []],
        ], $this->decodeJson($client->getResponse()->getContent()));
    }

    // ── /details additional error branches ───────────────────────────────────

    public function testDetailsReturnsBadRequestForInvalidUuid(): void
    {
        $client = $this->createBearerAuthenticatedClient();

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->willThrowException(new \App\Application\Exception\InvalidUuidException('Invalid UUID'));
        $this->replaceService(QueryBus::class, $queryBus);

        // Use a valid-format UUID so the route matches; the mock throws InvalidUuidException
        $client->request('GET', '/api/video/11111111-1111-4111-8111-111111111111');

        self::assertResponseStatusCodeSame(400);
        self::assertSame([
            'error' => ['code' => 'INVALID_UUID', 'message' => 'Invalid UUID', 'details' => []],
        ], $this->decodeJson($client->getResponse()->getContent()));
    }

    public function testDetailsReturnsForbiddenOnAccessDenied(): void
    {
        $client = $this->createBearerAuthenticatedClient();

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->willThrowException(new \App\Application\Exception\VideoAccessDeniedException('Access denied'));
        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('GET', '/api/video/11111111-1111-4111-8111-111111111111');

        self::assertResponseStatusCodeSame(403);
        self::assertSame([
            'error' => ['code' => 'ACCESS_DENIED', 'message' => 'Access denied', 'details' => []],
        ], $this->decodeJson($client->getResponse()->getContent()));
    }

    public function testDetailsReturnsTariffNotFound(): void
    {
        $client = $this->createBearerAuthenticatedClient();

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->willThrowException(new \App\Domain\User\Exception\TariffNotFound('Tariff not found'));
        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('GET', '/api/video/11111111-1111-4111-8111-111111111111');

        self::assertResponseStatusCodeSame(404);
        self::assertSame([
            'error' => ['code' => 'TARIFF_NOT_FOUND', 'message' => 'Tariff not found', 'details' => []],
        ], $this->decodeJson($client->getResponse()->getContent()));
    }

    public function testDetailsReturnsServerErrorOnUnexpectedException(): void
    {
        $client = $this->createBearerAuthenticatedClient();

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->willThrowException(new \RuntimeException('boom'));
        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('GET', '/api/video/11111111-1111-4111-8111-111111111111');

        self::assertResponseStatusCodeSame(500);
        self::assertSame([
            'error' => ['code' => 'INTERNAL_ERROR', 'message' => 'Failed to get video details', 'details' => []],
        ], $this->decodeJson($client->getResponse()->getContent()));
    }

    // ── /transcode additional error branches ─────────────────────────────────

    public function testTranscodeReturnsNotFoundForMissingVideo(): void
    {
        $client = $this->createBearerAuthenticatedClient();

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->willThrowException(new VideoNotFoundException('Video not found'));
        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('POST', '/api/video/11111111-1111-4111-8111-111111111111/transcode/77777777-7777-4777-8777-777777777777');

        self::assertResponseStatusCodeSame(404);
        self::assertSame([
            'error' => ['code' => 'VIDEO_NOT_FOUND', 'message' => 'Video not found', 'details' => []],
        ], $this->decodeJson($client->getResponse()->getContent()));
    }

    public function testTranscodeReturnsNotFoundForMissingPreset(): void
    {
        $client = $this->createBearerAuthenticatedClient();

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->willThrowException(new \App\Application\Exception\PresetNotFoundException('Preset not found'));
        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('POST', '/api/video/11111111-1111-4111-8111-111111111111/transcode/77777777-7777-4777-8777-777777777777');

        self::assertResponseStatusCodeSame(404);
        self::assertSame([
            'error' => ['code' => 'PRESET_NOT_FOUND', 'message' => 'Preset not found', 'details' => []],
        ], $this->decodeJson($client->getResponse()->getContent()));
    }

    public function testTranscodeReturnsNotFoundForMissingUser(): void
    {
        $client = $this->createBearerAuthenticatedClient();

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->willThrowException(new \App\Application\Exception\UserNotFoundException('User not found'));
        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('POST', '/api/video/11111111-1111-4111-8111-111111111111/transcode/77777777-7777-4777-8777-777777777777');

        self::assertResponseStatusCodeSame(404);
        self::assertSame([
            'error' => ['code' => 'USER_NOT_FOUND', 'message' => 'User not found', 'details' => []],
        ], $this->decodeJson($client->getResponse()->getContent()));
    }

    public function testTranscodeReturnsServerErrorForTaskCreationFailed(): void
    {
        $client = $this->createBearerAuthenticatedClient();

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->willThrowException(new \App\Application\Exception\TaskCreationFailedException('Task creation failed'));
        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('POST', '/api/video/11111111-1111-4111-8111-111111111111/transcode/77777777-7777-4777-8777-777777777777');

        self::assertResponseStatusCodeSame(500);
        self::assertSame([
            'error' => ['code' => 'TASK_CREATION_FAILED', 'message' => 'Task creation failed', 'details' => []],
        ], $this->decodeJson($client->getResponse()->getContent()));
    }

    // ── /patch additional error branches ─────────────────────────────────────

    public function testPatchReturnsNotFoundWhenVideoDoesNotExist(): void
    {
        $client = $this->createBearerAuthenticatedClient();
        $videoId = Uuid::fromString('11111111-1111-4111-8111-111111111111');

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->willThrowException(new VideoNotFoundException('Video not found'));
        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('PATCH', '/api/video/' . $videoId->toRfc4122(), [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['title' => 'New']));

        self::assertResponseStatusCodeSame(404);
        self::assertSame([
            'error' => ['code' => 'VIDEO_NOT_FOUND', 'message' => 'Video not found', 'details' => []],
        ], $this->decodeJson($client->getResponse()->getContent()));
    }

    public function testPatchReturnsServerErrorOnUnexpectedException(): void
    {
        $client = $this->createBearerAuthenticatedClient();
        $videoId = Uuid::fromString('11111111-1111-4111-8111-111111111111');

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->willThrowException(new \RuntimeException('boom'));
        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('PATCH', '/api/video/' . $videoId->toRfc4122(), [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['title' => 'New']));

        self::assertResponseStatusCodeSame(500);
        self::assertSame([
            'error' => ['code' => 'INTERNAL_ERROR', 'message' => 'Failed to patch video', 'details' => []],
        ], $this->decodeJson($client->getResponse()->getContent()));
    }

    // ── /patch additional error branches (InvalidUuid, DomainException) ───────

    public function testPatchReturnsBadRequestForInvalidVideoId(): void
    {
        $client = $this->createBearerAuthenticatedClient();

        // Route has no UUID requirement on {id}, so 'not-a-uuid' reaches the controller.
        // PatchVideoQuery constructor calls Uuid::fromString('not-a-uuid') which throws InvalidUuidException.
        $client->request('PATCH', '/api/video/not-a-uuid', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['title' => 'New']));

        self::assertResponseStatusCodeSame(400);
        self::assertSame([
            'error' => ['code' => 'INVALID_VIDEO_ID', 'message' => 'Invalid UUID', 'details' => []],
        ], $this->decodeJson($client->getResponse()->getContent()));
    }

    public function testPatchReturnsForbiddenOnDomainException(): void
    {
        $client = $this->createBearerAuthenticatedClient();
        $videoId = Uuid::fromString('11111111-1111-4111-8111-111111111111');

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->willThrowException(new \DomainException('Not allowed to patch'));
        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('PATCH', '/api/video/' . $videoId->toRfc4122(), [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['title' => 'New']));

        self::assertResponseStatusCodeSame(403);
        self::assertSame([
            'error' => ['code' => 'ACCESS_DENIED', 'message' => 'Not allowed to patch', 'details' => []],
        ], $this->decodeJson($client->getResponse()->getContent()));
    }

    // ── /delete additional error branches (TranscodeAccessDenied, VideoHasTranscodingTasks) ───

    public function testDeleteReturnsForbiddenOnTranscodeAccessDenied(): void
    {
        $client = $this->createBearerAuthenticatedClient();
        $videoId = Uuid::fromString('11111111-1111-4111-8111-111111111111');

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->willThrowException(new TranscodeAccessDeniedException('Transcode access denied'));
        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('DELETE', '/api/video/' . $videoId->toRfc4122());

        self::assertResponseStatusCodeSame(403);
        self::assertSame([
            'error' => ['code' => 'ACCESS_DENIED', 'message' => 'Transcode access denied', 'details' => []],
        ], $this->decodeJson($client->getResponse()->getContent()));
    }

    public function testDeleteReturnsConflictOnVideoHasTranscodingTasks(): void
    {
        $client = $this->createBearerAuthenticatedClient();
        $videoId = Uuid::fromString('11111111-1111-4111-8111-111111111111');

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->willThrowException(VideoHasTranscodingTasks::forVideo());
        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('DELETE', '/api/video/' . $videoId->toRfc4122());

        self::assertResponseStatusCodeSame(409);
        self::assertSame([
            'error' => ['code' => 'VIDEO_HAS_TRANSCODING_TASKS', 'message' => 'Video has active transcoding tasks and cannot be deleted.', 'details' => []],
        ], $this->decodeJson($client->getResponse()->getContent()));
    }

    /**
     * @return array<mixed>
     * @throws \JsonException
     */
    private function decodeJson(?string $content): array
    {
        self::assertNotNull($content);

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
