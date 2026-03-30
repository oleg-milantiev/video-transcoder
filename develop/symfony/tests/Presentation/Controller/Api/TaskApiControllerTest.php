<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller\Api;

use App\Application\Exception\QueryException;
use App\Application\Logging\LogServiceInterface;
use App\Application\Query\GetTaskListQuery;
use App\Application\QueryHandler\QueryBus;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\ValueObject\Progress;
use App\Domain\Video\ValueObject\TaskDates;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Infrastructure\Security\Voter\VideoAccessVoter;
use App\Tests\Domain\Entity\TaskFake;
use App\Tests\Domain\Entity\VideoFake;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

final class TaskApiControllerTest extends ApiWebTestCase
{
    public function testListReturnsPaginatedPayload(): void
    {
        $client = $this->createBearerAuthenticatedClient();

        $items = [
            ['id' => 11, 'status' => 'PENDING'],
            ['id' => 12, 'status' => 'PROCESSING'],
        ];

        $listPayload = [
            'items' => $items,
            'total' => 9,
            'page' => 1,
            'limit' => 2,
            'totalPages' => 5,
        ];

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->with($this->callback(function (object $query): bool {
                return $query instanceof GetTaskListQuery
                    && $query->page === 1
                    && $query->limit === 2;
            }))
            ->willReturn((object) $listPayload);

        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('GET', '/api/task/?page=1&limit=2');

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
            ->willThrowException(new QueryException('Task list failed'));
        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('GET', '/api/task/');

        self::assertResponseStatusCodeSame(400);
        self::assertSame([
            'error' => [
                'code' => 'QUERY_FAILED',
                'message' => 'Task list failed',
                'details' => [],
            ],
        ], $this->decodeJson($client->getResponse()->getContent()));
    }

    public function testCancelReturnsNotFoundWhenTaskDoesNotExist(): void
    {
        $client = $this->createBearerAuthenticatedClient(userId: SymfonyUuid::fromString('00000000-0000-4000-8000-000000000042'));

        $taskId = Uuid::fromString('40404040-4040-4040-8040-404040404040');

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->once())->method('findById')->with($taskId)->willReturn(null);
        $this->replaceService(TaskRepositoryInterface::class, $taskRepository);

        $client->request('POST', '/api/task/' . $taskId->toRfc4122() . '/cancel');

        self::assertResponseStatusCodeSame(404);
        self::assertSame([
            'error' => [
                'code' => 'TASK_NOT_FOUND',
                'message' => 'Task not found',
                'details' => [],
            ],
        ], $this->decodeJson($client->getResponse()->getContent()));
    }

    public function testCancelReturnsNotFoundWhenVideoDoesNotExist(): void
    {
        $client = $this->createBearerAuthenticatedClient(userId: SymfonyUuid::fromString('00000000-0000-4000-8000-000000000042'));

        $taskId = Uuid::fromString('15151515-1515-4515-8515-151515151515');

        $task = TaskFake::create();

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->once())->method('findById')->with($taskId)->willReturn($task);
        $this->replaceService(TaskRepositoryInterface::class, $taskRepository);

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->once())->method('findById')->with($task->videoId())->willReturn(null);
        $this->replaceService(VideoRepositoryInterface::class, $videoRepository);

        $client->request('POST', '/api/task/' . $taskId->toRfc4122() . '/cancel');

        self::assertResponseStatusCodeSame(404);
        self::assertSame([
            'error' => [
                'code' => 'VIDEO_NOT_FOUND',
                'message' => 'Video not found',
                'details' => [],
            ],
        ], $this->decodeJson($client->getResponse()->getContent()));
    }

    public function testCancelReturnsForbiddenWhenAccessIsDenied(): void
    {
        $client = $this->createBearerAuthenticatedClient(userId: SymfonyUuid::fromString('00000000-0000-4000-8000-000000000042'));

        $taskId = Uuid::fromString('18181818-1818-4818-8818-181818181818');

        $task = TaskFake::create();
        $video = VideoFake::create();

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->once())->method('findById')->with($taskId)->willReturn($task);
        $this->replaceService(TaskRepositoryInterface::class, $taskRepository);

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->once())->method('findById')->with($task->videoId())->willReturn($video);
        $this->replaceService(VideoRepositoryInterface::class, $videoRepository);

        $security = $this->createMock(Security::class);
        $security->expects($this->once())
            ->method('isGranted')
            ->with(VideoAccessVoter::CAN_CANCEL_TRANSCODE, $video)
            ->willReturn(false);
        $this->replaceService(Security::class, $security);

        $client->request('POST', '/api/task/' . $taskId->toRfc4122() . '/cancel');

        self::assertResponseStatusCodeSame(403);
        self::assertSame([
            'error' => [
                'code' => 'ACCESS_DENIED',
                'message' => 'Access denied',
                'details' => [],
            ],
        ], $this->decodeJson($client->getResponse()->getContent()));
    }

    public function testCancelPendingTaskCancelsImmediately(): void
    {
        $client = $this->createBearerAuthenticatedClient(userId: SymfonyUuid::fromString('00000000-0000-4000-8000-000000000042'));

        $taskId = Uuid::fromString('21212121-2121-4212-8212-212121212121');

        $task = TaskFake::create();
        $video = VideoFake::create();

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->once())->method('findById')->with($taskId)->willReturn($task);
        $taskRepository->expects($this->once())->method('save')->with($task);
        $this->replaceService(TaskRepositoryInterface::class, $taskRepository);

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->atLeast(3))->method('log');
        $this->replaceService(LogServiceInterface::class, $logService);

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->exactly(2))->method('findById')->with($task->videoId())->willReturn($video);
        $this->replaceService(VideoRepositoryInterface::class, $videoRepository);

        $security = $this->createMock(Security::class);
        $security->expects($this->once())
            ->method('isGranted')
            ->with(VideoAccessVoter::CAN_CANCEL_TRANSCODE, $video)
            ->willReturn(true);
        $this->replaceService(Security::class, $security);

        $client->request('POST', '/api/task/' . $taskId->toRfc4122() . '/cancel');

        self::assertResponseStatusCodeSame(200);

        $payload = $this->decodeJson($client->getResponse()->getContent());
        self::assertSame('CANCELLED', $payload['data']['task']['status']);
        self::assertTrue($payload['data']['task']['cancelledNow']);
        self::assertTrue($payload['data']['task']['cancellationRequested']);
        self::assertSame('00000000-0000-4000-8000-000000000042', $task->meta()['cancelledByUserId']);
        self::assertArrayHasKey('cancelRequestedAt', $task->meta());
    }

    public function testCancelProcessingTaskMarksRequestWithoutImmediateCancel(): void
    {
        $client = $this->createBearerAuthenticatedClient(userId: SymfonyUuid::fromString('00000000-0000-4000-8000-000000000042'));

        $taskId = Uuid::fromString('25252525-2525-4252-8252-252525252525');

        $task = $this->createProcessingTask();
        $video = VideoFake::create();

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->once())->method('findById')->with($taskId)->willReturn($task);
        $taskRepository->expects($this->once())->method('save')->with($task);
        $this->replaceService(TaskRepositoryInterface::class, $taskRepository);

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->atLeast(3))->method('log');
        $this->replaceService(LogServiceInterface::class, $logService);

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->exactly(2))->method('findById')->with($task->videoId())->willReturn($video);
        $this->replaceService(VideoRepositoryInterface::class, $videoRepository);

        $security = $this->createMock(Security::class);
        $security->expects($this->once())
            ->method('isGranted')
            ->with(VideoAccessVoter::CAN_CANCEL_TRANSCODE, $video)
            ->willReturn(true);
        $this->replaceService(Security::class, $security);

        $client->request('POST', '/api/task/' . $taskId->toRfc4122() . '/cancel');

        self::assertResponseStatusCodeSame(200);

        $payload = $this->decodeJson($client->getResponse()->getContent());
        self::assertSame('PROCESSING', $payload['data']['task']['status']);
        self::assertFalse($payload['data']['task']['cancelledNow']);
        self::assertTrue($payload['data']['task']['cancellationRequested']);
        self::assertSame('00000000-0000-4000-8000-000000000042', $task->meta()['cancelledByUserId']);
        self::assertArrayHasKey('cancelRequestedAt', $task->meta());
    }

    private function createProcessingTask(): Task
    {
        $task = Task::reconstitute(
            videoId: Uuid::fromString('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'),
            presetId: Uuid::fromString('10101010-1010-4010-8010-101010101010'),
            userId: Uuid::fromString('00000000-0000-4000-8000-000000000042'),
            status: TaskStatus::STARTING,
            progress: new Progress(0),
            dates: TaskDates::create(),
            id: Uuid::fromString('77777777-7777-4777-8777-777777777777'),
        );
        $task->start(12.5);

        return $task;
    }

    private function decodeJson(?string $content): array
    {
        self::assertNotNull($content);

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}



