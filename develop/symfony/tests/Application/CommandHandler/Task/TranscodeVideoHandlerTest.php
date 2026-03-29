<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler\Task;

use App\Application\Command\Task\StartTaskScheduler;
use App\Application\Command\Task\TranscodeVideo;
use App\Application\CommandHandler\Task\TranscodeVideoHandler;
use App\Application\DTO\ScheduledTaskDTO;
use App\Application\DTO\TranscodeProcessReportDTO;
use App\Application\DTO\TranscodeReportDTO;
use App\Application\DTO\TranscodeStartContextDTO;
use App\Application\Event\TranscodeVideoFail;
use App\Application\Event\TranscodeVideoStart;
use App\Application\Event\TranscodeVideoSuccess;
use App\Application\Logging\LogServiceInterface;
use App\Application\Service\Task\TranscodeProcessService;
use App\Application\Service\Task\TranscodeTaskFinalizationService;
use App\Application\Service\Task\TranscodeTaskPreparationService;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Video\ValueObject\VideoTitle;
use App\Infrastructure\Task\TaskCancellationTrigger;
use App\Tests\Domain\Entity\PresetFake;
use App\Tests\Domain\Entity\TaskFake;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class TranscodeVideoHandlerTest extends TestCase
{
    private function makeScheduledTask(): ScheduledTaskDTO
    {
        return new ScheduledTaskDTO(
            taskId: Uuid::fromString('12345678-e89b-42d3-a456-426614174001'),
            userId: Uuid::fromString('12345678-e89b-42d3-a456-426614174002'),
            videoId: Uuid::fromString('12345678-e89b-42d3-a456-426614174003'),
        );
    }

    /**
     * Creates a LockFactory stub/mock.
     * When $expectRelease=false, uses stubs (no expectation verification).
     * When $expectRelease=true, uses mocks and asserts release() is called exactly once.
     */
    private function makeLockFactory(bool $acquired = true, bool $expectRelease = false): LockFactory
    {
        if ($expectRelease) {
            $lock = $this->createMock(SharedLockInterface::class);
            $lock->expects($this->atLeastOnce())->method('acquire')->willReturn($acquired);
            $lock->expects($this->once())->method('release');

            $lockFactory = $this->createMock(LockFactory::class);
            $lockFactory->expects($this->once())->method('createLock')->willReturn($lock);
        } else {
            $lock = $this->createStub(SharedLockInterface::class);
            $lock->method('acquire')->willReturn($acquired);

            $lockFactory = $this->createStub(LockFactory::class);
            $lockFactory->method('createLock')->willReturn($lock);
        }

        return $lockFactory;
    }

    /** Returns an anonymous event-bus that records dispatched class names. */
    private function makeSpyBus(array &$dispatched): MessageBusInterface
    {
        return new class ($dispatched) implements MessageBusInterface {
            public function __construct(private array &$dispatched) {}

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->dispatched[] = $message::class;
                return new Envelope($message);
            }
        };
    }

    private function makeVideoWithDuration(float $duration = 120.0): Video
    {
        return Video::reconstitute(
            new VideoTitle('test-video.mp4'),
            new FileExtension('mp4'),
            Uuid::generate(),
            ['duration' => $duration],
            VideoDates::create(),
            Uuid::generate(),
        );
    }

    private function makeVideoWithoutDuration(): Video
    {
        return Video::reconstitute(
            new VideoTitle('test-video.mp4'),
            new FileExtension('mp4'),
            Uuid::generate(),
            [],
            VideoDates::create(),
            Uuid::generate(),
        );
    }

    private function makeSuccessReport(): TranscodeReportDTO
    {
        return new TranscodeReportDTO(
            cancelled: false,
            ffmpeg: [],
            process: new TranscodeProcessReportDTO(1.5, 0, 'OK', 'ffmpeg -i in.mp4 out.mp4', '', ''),
        );
    }

    private function makeCancelledReport(): TranscodeReportDTO
    {
        return new TranscodeReportDTO(
            cancelled: true,
            ffmpeg: [],
            process: new TranscodeProcessReportDTO(0.5, 1, 'Cancelled', 'ffmpeg -i in.mp4 out.mp4', '', ''),
        );
    }

    private function makeHandler(
        MessageBusInterface $commandBus,
        MessageBusInterface $eventBus,
        TaskRepositoryInterface $taskRepository,
        VideoRepositoryInterface $videoRepository,
        LogServiceInterface $logService,
        LoggerInterface $logger,
        LockFactory $lockFactory,
        TaskCancellationTrigger $cancellationTrigger,
        TranscodeProcessService $transcodeProcessService,
        TranscodeTaskPreparationService $transcodeTaskPreparationService,
        TranscodeTaskFinalizationService $transcodeTaskFinalizationService,
    ): TranscodeVideoHandler {
        return new TranscodeVideoHandler(
            $commandBus,
            $eventBus,
            $taskRepository,
            $videoRepository,
            $logService,
            $logger,
            $lockFactory,
            $cancellationTrigger,
            $transcodeProcessService,
            $transcodeTaskPreparationService,
            $transcodeTaskFinalizationService,
        );
    }

    public function testDispatchesStartThenFailWhenTaskNotFound(): void
    {
        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRepository->method('findByIdFresh')->willReturn(null);

        $events = [];
        $eventBus = $this->makeSpyBus($events);

        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->never())->method('dispatch');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->never())->method('createLock');

        $handler = $this->makeHandler(
            $commandBus,
            $eventBus,
            $taskRepository,
            $this->createStub(VideoRepositoryInterface::class),
            $this->createStub(LogServiceInterface::class),
            $this->createStub(LoggerInterface::class),
            $lockFactory,
            new TaskCancellationTrigger(new ArrayAdapter()),
            $this->createStub(TranscodeProcessService::class),
            $this->createStub(TranscodeTaskPreparationService::class),
            $this->createStub(TranscodeTaskFinalizationService::class),
        );

        $handler(new TranscodeVideo($this->makeScheduledTask()));

        $this->assertSame([TranscodeVideoStart::class, TranscodeVideoFail::class], $events);
    }

    public function testDispatchesStartThenFailWhenLockNotAcquired(): void
    {
        $task = TaskFake::create();

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRepository->method('findByIdFresh')->willReturn($task);

        $events = [];
        $eventBus = $this->makeSpyBus($events);

        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->never())->method('dispatch');

        $handler = $this->makeHandler(
            $commandBus,
            $eventBus,
            $taskRepository,
            $this->createStub(VideoRepositoryInterface::class),
            $this->createStub(LogServiceInterface::class),
            $this->createStub(LoggerInterface::class),
            $this->makeLockFactory(acquired: false),
            new TaskCancellationTrigger(new ArrayAdapter()),
            $this->createStub(TranscodeProcessService::class),
            $this->createStub(TranscodeTaskPreparationService::class),
            $this->createStub(TranscodeTaskFinalizationService::class),
        );

        $handler(new TranscodeVideo($this->makeScheduledTask()));

        $this->assertSame([TranscodeVideoStart::class, TranscodeVideoFail::class], $events);
    }

    public function testDispatchesFailAndThrowsRuntimeExceptionWhenVideoNotFound(): void
    {
        $task = TaskFake::create();

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRepository->method('findByIdFresh')->willReturn($task);

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $videoRepository->method('findById')->willReturn(null);

        $events = [];
        $eventBus = $this->makeSpyBus($events);

        $handler = $this->makeHandler(
            $this->createStub(MessageBusInterface::class),
            $eventBus,
            $taskRepository,
            $videoRepository,
            $this->createStub(LogServiceInterface::class),
            $this->createStub(LoggerInterface::class),
            $this->makeLockFactory(acquired: true),
            new TaskCancellationTrigger(new ArrayAdapter()),
            $this->createStub(TranscodeProcessService::class),
            $this->createStub(TranscodeTaskPreparationService::class),
            $this->createStub(TranscodeTaskFinalizationService::class),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Video not found for transcoding');

        try {
            $handler(new TranscodeVideo($this->makeScheduledTask()));
        } finally {
            $this->assertSame([TranscodeVideoStart::class, TranscodeVideoFail::class], $events);
        }
    }

    public function testCancellationBeforeStartWhenTaskIsCancellable(): void
    {
        $task = TaskFake::create(); // PENDING → canBeCancelled() = true

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->method('findByIdFresh')->willReturn($task);
        $taskRepository->expects($this->once())->method('save')->with($task);

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $videoRepository->method('findById')->willReturn($this->makeVideoWithDuration());

        $events = [];
        $eventBus = $this->makeSpyBus($events);

        $cancellationTrigger = new TaskCancellationTrigger(new ArrayAdapter());
        $cancellationTrigger->request($task->id());

        $handler = $this->makeHandler(
            $this->createStub(MessageBusInterface::class),
            $eventBus,
            $taskRepository,
            $videoRepository,
            $this->createStub(LogServiceInterface::class),
            $this->createStub(LoggerInterface::class),
            $this->makeLockFactory(acquired: true),
            $cancellationTrigger,
            $this->createStub(TranscodeProcessService::class),
            $this->createStub(TranscodeTaskPreparationService::class),
            $this->createStub(TranscodeTaskFinalizationService::class),
        );

        $handler(new TranscodeVideo($this->makeScheduledTask()));

        $this->assertSame([TranscodeVideoStart::class, TranscodeVideoFail::class], $events);
        $this->assertFalse($cancellationTrigger->isRequested($task->id()), 'Cancellation trigger should be cleared after handling');
    }

    public function testDispatchesFailWhenTaskCannotStart(): void
    {
        $task = TaskFake::create(); // PENDING status

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRepository->method('findByIdFresh')->willReturn($task);

        // Video with no duration → task.canStart(null) = false
        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $videoRepository->method('findById')->willReturn($this->makeVideoWithoutDuration());

        $events = [];
        $eventBus = $this->makeSpyBus($events);

        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->never())->method('dispatch');

        $handler = $this->makeHandler(
            $commandBus,
            $eventBus,
            $taskRepository,
            $videoRepository,
            $this->createStub(LogServiceInterface::class),
            $this->createStub(LoggerInterface::class),
            $this->makeLockFactory(acquired: true),
            new TaskCancellationTrigger(new ArrayAdapter()),
            $this->createStub(TranscodeProcessService::class),
            $this->createStub(TranscodeTaskPreparationService::class),
            $this->createStub(TranscodeTaskFinalizationService::class),
        );

        $handler(new TranscodeVideo($this->makeScheduledTask()));

        $this->assertSame([TranscodeVideoStart::class, TranscodeVideoFail::class], $events);
    }

    public function testSuccessfulTranscoding(): void
    {
        $task = TaskFake::create();
        $video = $this->makeVideoWithDuration();
        $report = $this->makeSuccessReport();
        $context = new TranscodeStartContextDTO($task, $video, new PresetFake(), 'output/path.mp4', '/abs/output/path.mp4', '/input/path.mp4');

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRepository->method('findByIdFresh')->willReturn($task);

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $videoRepository->method('findById')->willReturn($video);

        $transcodeTaskPreparationService = $this->createMock(TranscodeTaskPreparationService::class);
        $transcodeTaskPreparationService->expects($this->once())->method('prepare')->with($task, $video)->willReturn($context);

        $transcodeProcessService = $this->createMock(TranscodeProcessService::class);
        $transcodeProcessService->expects($this->once())->method('run')->with($context)->willReturn($report);

        $transcodeTaskFinalizationService = $this->createMock(TranscodeTaskFinalizationService::class);
        $transcodeTaskFinalizationService->expects($this->once())->method('handleSuccess')->with($task, $context, $report);
        $transcodeTaskFinalizationService->expects($this->never())->method('handleCancellation');
        $transcodeTaskFinalizationService->expects($this->never())->method('handleFailure');

        $events = [];
        $eventBus = $this->makeSpyBus($events);

        $commandBusDispatched = [];
        $commandBus = $this->makeSpyBus($commandBusDispatched);

        $handler = $this->makeHandler(
            $commandBus,
            $eventBus,
            $taskRepository,
            $videoRepository,
            $this->createStub(LogServiceInterface::class),
            $this->createStub(LoggerInterface::class),
            $this->makeLockFactory(acquired: true, expectRelease: true),
            new TaskCancellationTrigger(new ArrayAdapter()),
            $transcodeProcessService,
            $transcodeTaskPreparationService,
            $transcodeTaskFinalizationService,
        );

        $handler(new TranscodeVideo($this->makeScheduledTask()));

        $this->assertSame([TranscodeVideoStart::class, TranscodeVideoSuccess::class], $events);
        $this->assertContains(StartTaskScheduler::class, $commandBusDispatched);
    }

    public function testTranscodingCancelledDuringProcess(): void
    {
        $task = TaskFake::create();
        $video = $this->makeVideoWithDuration();
        $report = $this->makeCancelledReport();
        $context = new TranscodeStartContextDTO($task, $video, new PresetFake(), 'output/path.mp4', '/abs/output/path.mp4', '/input/path.mp4');

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRepository->method('findByIdFresh')->willReturn($task);

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $videoRepository->method('findById')->willReturn($video);

        $transcodeTaskPreparationService = $this->createMock(TranscodeTaskPreparationService::class);
        $transcodeTaskPreparationService->expects($this->once())->method('prepare')->willReturn($context);

        $transcodeProcessService = $this->createMock(TranscodeProcessService::class);
        $transcodeProcessService->expects($this->once())->method('run')->willReturn($report);

        $transcodeTaskFinalizationService = $this->createMock(TranscodeTaskFinalizationService::class);
        $transcodeTaskFinalizationService->expects($this->once())->method('handleCancellation')->with($task, $report);
        $transcodeTaskFinalizationService->expects($this->never())->method('handleSuccess');
        $transcodeTaskFinalizationService->expects($this->never())->method('handleFailure');

        $events = [];
        $eventBus = $this->makeSpyBus($events);

        $commandBusDispatched = [];
        $commandBus = $this->makeSpyBus($commandBusDispatched);

        $handler = $this->makeHandler(
            $commandBus,
            $eventBus,
            $taskRepository,
            $videoRepository,
            $this->createStub(LogServiceInterface::class),
            $this->createStub(LoggerInterface::class),
            $this->makeLockFactory(acquired: true, expectRelease: true),
            new TaskCancellationTrigger(new ArrayAdapter()),
            $transcodeProcessService,
            $transcodeTaskPreparationService,
            $transcodeTaskFinalizationService,
        );

        $handler(new TranscodeVideo($this->makeScheduledTask()));

        $this->assertSame([TranscodeVideoStart::class, TranscodeVideoFail::class], $events);
        $this->assertContains(StartTaskScheduler::class, $commandBusDispatched);
    }

    public function testLockReleaseExceptionIsLoggedAndSwallowed(): void
    {
        $task = TaskFake::create();
        $video = $this->makeVideoWithDuration();
        $report = $this->makeSuccessReport();
        $context = new TranscodeStartContextDTO($task, $video, new PresetFake(), 'output/path.mp4', '/abs/output/path.mp4', '/input/path.mp4');

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRepository->method('findByIdFresh')->willReturn($task);

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $videoRepository->method('findById')->willReturn($video);

        // Lock that successfully acquires but throws on release
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->atLeastOnce())->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release')->willThrowException(new \RuntimeException('lock backend unavailable'));

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())->method('createLock')->willReturn($lock);

        $transcodeTaskPreparationService = $this->createMock(TranscodeTaskPreparationService::class);
        $transcodeTaskPreparationService->expects($this->once())->method('prepare')->willReturn($context);

        $transcodeProcessService = $this->createMock(TranscodeProcessService::class);
        $transcodeProcessService->expects($this->once())->method('run')->willReturn($report);

        $transcodeTaskFinalizationService = $this->createMock(TranscodeTaskFinalizationService::class);
        $transcodeTaskFinalizationService->expects($this->once())->method('handleSuccess');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with(
            'Failed to release transcode task mutex',
            $this->arrayHasKey('taskId'),
        );

        $events = [];
        $eventBus = $this->makeSpyBus($events);

        $commandBusDispatched = [];
        $commandBus = $this->makeSpyBus($commandBusDispatched);

        $handler = $this->makeHandler(
            $commandBus,
            $eventBus,
            $taskRepository,
            $videoRepository,
            $this->createStub(LogServiceInterface::class),
            $logger,
            $lockFactory,
            new TaskCancellationTrigger(new ArrayAdapter()),
            $transcodeProcessService,
            $transcodeTaskPreparationService,
            $transcodeTaskFinalizationService,
        );

        // Exception from lock release must be swallowed — no exception propagated
        $handler(new TranscodeVideo($this->makeScheduledTask()));

        $this->assertSame([TranscodeVideoStart::class, TranscodeVideoSuccess::class], $events);
        $this->assertContains(StartTaskScheduler::class, $commandBusDispatched);
    }

    public function testTranscodingExceptionDispatchesFailAndRethrows(): void
    {
        $task = TaskFake::create();
        $video = $this->makeVideoWithDuration();
        $context = new TranscodeStartContextDTO($task, $video, new PresetFake(), 'output/path.mp4', '/abs/output/path.mp4', '/input/path.mp4');

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRepository->method('findByIdFresh')->willReturn($task);

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $videoRepository->method('findById')->willReturn($video);

        $transcodeTaskPreparationService = $this->createMock(TranscodeTaskPreparationService::class);
        $transcodeTaskPreparationService->expects($this->once())->method('prepare')->willReturn($context);

        $transcodeProcessService = $this->createMock(TranscodeProcessService::class);
        $transcodeProcessService->expects($this->once())->method('run')->willThrowException(new \RuntimeException('ffmpeg process failed'));

        $transcodeTaskFinalizationService = $this->createMock(TranscodeTaskFinalizationService::class);
        $transcodeTaskFinalizationService->expects($this->once())->method('handleFailure')->with($task, $this->isInstanceOf(\RuntimeException::class), $context);
        $transcodeTaskFinalizationService->expects($this->never())->method('handleSuccess');
        $transcodeTaskFinalizationService->expects($this->never())->method('handleCancellation');

        $events = [];
        $eventBus = $this->makeSpyBus($events);

        $handler = $this->makeHandler(
            $this->createStub(MessageBusInterface::class),
            $eventBus,
            $taskRepository,
            $videoRepository,
            $this->createStub(LogServiceInterface::class),
            $this->createStub(LoggerInterface::class),
            $this->makeLockFactory(acquired: true, expectRelease: true),
            new TaskCancellationTrigger(new ArrayAdapter()),
            $transcodeProcessService,
            $transcodeTaskPreparationService,
            $transcodeTaskFinalizationService,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ffmpeg process failed');

        try {
            $handler(new TranscodeVideo($this->makeScheduledTask()));
        } finally {
            $this->assertSame([TranscodeVideoStart::class, TranscodeVideoFail::class], $events);
        }
    }
}
