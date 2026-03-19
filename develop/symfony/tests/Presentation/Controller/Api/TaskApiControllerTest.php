<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller\Api;

use App\Application\Exception\QueryException;
use App\Application\Query\GetTaskListQuery;
use App\Application\QueryHandler\QueryBus;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Infrastructure\Security\Voter\VideoAccessVoter;
use App\Tests\Domain\Entity\TaskFake;
use App\Tests\Domain\Entity\VideoFake;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\UuidV4;

final class TaskApiControllerTest extends ApiWebTestCase
{
    public function testListReturnsItems(): void
    {
        $client = $this->createAuthenticatedClient();

        $items = [
            ['id' => 11, 'status' => 'PENDING'],
            ['id' => 12, 'status' => 'PROCESSING'],
        ];

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->with($this->callback(function (object $query): bool {
                return $query instanceof GetTaskListQuery
                    && $query->page === 1
                    && $query->limit === 2;
            }))
            ->willReturn((object) ['items' => $items]);

        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('GET', '/api/task/?page=1&limit=2');

        self::assertResponseStatusCodeSame(200);
        self::assertSame($items, $this->decodeJson($client->getResponse()->getContent()));
    }

    /**
     * @throws \JsonException
     */
    public function testListReturnsBadRequestOnQueryException(): void
    {
        $client = $this->createAuthenticatedClient();

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->willThrowException(new QueryException('Task list failed'));
        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('GET', '/api/task/');

        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => 'Task list failed'], $this->decodeJson($client->getResponse()->getContent()));
    }

    public function testCancelReturnsNotFoundWhenTaskDoesNotExist(): void
    {
        $client = $this->createAuthenticatedClient(userId: 42);

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->once())->method('findById')->with(404)->willReturn(null);
        $this->replaceService(TaskRepositoryInterface::class, $taskRepository);

        $client->request('POST', '/api/task/404/cancel');

        self::assertResponseStatusCodeSame(404);
    }

    public function testCancelReturnsNotFoundWhenVideoDoesNotExist(): void
    {
        $client = $this->createAuthenticatedClient(userId: 42);

        $task = new TaskFake();

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->once())->method('findById')->with(15)->willReturn($task);
        $this->replaceService(TaskRepositoryInterface::class, $taskRepository);

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->once())->method('findById')->with($task->videoId())->willReturn(null);
        $this->replaceService(VideoRepositoryInterface::class, $videoRepository);

        $client->request('POST', '/api/task/15/cancel');

        self::assertResponseStatusCodeSame(404);
    }

    public function testCancelReturnsForbiddenWhenAccessIsDenied(): void
    {
        $client = $this->createAuthenticatedClient(userId: 42);

        $task = new TaskFake();
        $video = new VideoFake();

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->once())->method('findById')->with(18)->willReturn($task);
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

        $client->request('POST', '/api/task/18/cancel');

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @throws \JsonException
     */
    public function testCancelPendingTaskCancelsImmediately(): void
    {
        $client = $this->createAuthenticatedClient(userId: 42);

        $task = new TaskFake();
        $video = new VideoFake();

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->once())->method('findById')->with(21)->willReturn($task);
        $taskRepository->expects($this->once())->method('save')->with($task);
        $taskRepository->expects($this->exactly(2))->method('log');
        $this->replaceService(TaskRepositoryInterface::class, $taskRepository);

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->once())->method('findById')->with($task->videoId())->willReturn($video);
        $this->replaceService(VideoRepositoryInterface::class, $videoRepository);

        $security = $this->createMock(Security::class);
        $security->expects($this->once())
            ->method('isGranted')
            ->with(VideoAccessVoter::CAN_CANCEL_TRANSCODE, $video)
            ->willReturn(true);
        $this->replaceService(Security::class, $security);

        $client->request('POST', '/api/task/21/cancel');

        self::assertResponseStatusCodeSame(200);

        $payload = $this->decodeJson($client->getResponse()->getContent());
        self::assertSame('CANCELLED', $payload['status']);
        self::assertTrue($payload['cancelledNow']);
        self::assertTrue($payload['cancellationRequested']);
        self::assertSame(42, $task->meta()['cancelledByUserId']);
        self::assertArrayHasKey('cancelRequestedAt', $task->meta());
    }

    public function testCancelProcessingTaskMarksRequestWithoutImmediateCancel(): void
    {
        $client = $this->createAuthenticatedClient(userId: 42);

        $task = $this->createProcessingTask();
        $video = new VideoFake();

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->once())->method('findById')->with(25)->willReturn($task);
        $taskRepository->expects($this->once())->method('save')->with($task);
        $taskRepository->expects($this->once())->method('log');
        $this->replaceService(TaskRepositoryInterface::class, $taskRepository);

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->once())->method('findById')->with($task->videoId())->willReturn($video);
        $this->replaceService(VideoRepositoryInterface::class, $videoRepository);

        $security = $this->createMock(Security::class);
        $security->expects($this->once())
            ->method('isGranted')
            ->with(VideoAccessVoter::CAN_CANCEL_TRANSCODE, $video)
            ->willReturn(true);
        $this->replaceService(Security::class, $security);

        $client->request('POST', '/api/task/25/cancel');

        self::assertResponseStatusCodeSame(200);

        $payload = $this->decodeJson($client->getResponse()->getContent());
        self::assertSame('PROCESSING', $payload['status']);
        self::assertFalse($payload['cancelledNow']);
        self::assertTrue($payload['cancellationRequested']);
        self::assertSame(42, $task->meta()['cancelledByUserId']);
        self::assertArrayHasKey('cancelRequestedAt', $task->meta());
    }

    private function createProcessingTask(): Task
    {
        $task = Task::create(UuidV4::fromString('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'), 101, 42);
        $task->setId(777);
        $task->start();

        return $task;
    }

    /**
     * @throws \JsonException
     */
    private function decodeJson(?string $content): array
    {
        self::assertNotNull($content);

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}



