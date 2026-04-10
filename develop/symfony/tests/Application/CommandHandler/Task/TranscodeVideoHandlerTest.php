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
use App\Application\Exception\StorageSizeExceedsQuota;
use App\Application\Logging\LogServiceInterface;
use App\Application\Service\Task\TranscodeProcessService;
use App\Application\Service\Task\TranscodeTaskFinalizationService;
use App\Application\Service\Task\TranscodeTaskPreparationService;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\User\Entity\Tariff;
use App\Domain\User\Entity\User;
use App\Domain\User\Exception\TariffNotFound;
use App\Domain\User\Exception\UserNotFound;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\TariffDelay;
use App\Domain\User\ValueObject\TariffInstance;
use App\Domain\User\ValueObject\TariffMaxHeight;
use App\Domain\User\ValueObject\TariffMaxWidth;
use App\Domain\User\ValueObject\TariffStorageGb;
use App\Domain\User\ValueObject\TariffStorageHour;
use App\Domain\User\ValueObject\TariffTitle;
use App\Domain\User\ValueObject\TariffVideoDuration;
use App\Domain\User\ValueObject\TariffVideoSize;
use App\Domain\User\ValueObject\UserEmail;
use App\Domain\User\ValueObject\UserRoles;
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
use Psr\Log\LogLevel;
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

    private function makeVideoWithDuration(float $duration = 120.0, ?int $size = null): Video
    {
        $meta = ['duration' => $duration];
        if ($size !== null) {
            $meta['size'] = $size;
        }

        return Video::reconstitute(
            new VideoTitle('test-video.mp4'),
            new FileExtension('mp4'),
            Uuid::generate(),
            $meta,
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

    private function makeTariff(float $storageGb = 10.0): Tariff
    {
        return new Tariff(
            new TariffTitle('Pro'),
            new TariffDelay(60),
            new TariffInstance(2),
            new TariffVideoDuration(3600),
            new TariffVideoSize(500.0),
            new TariffMaxWidth(1920),
            new TariffMaxHeight(1080),
            new TariffStorageGb($storageGb),
            new TariffStorageHour(24),
        );
    }

    private function makeUser(?Tariff $tariff = null, ?Uuid $id = null): User
    {
        $scheduledTask = $this->makeScheduledTask();

        return new User(
            email: new UserEmail('user@example.com'),
            roles: new UserRoles(['ROLE_USER']),
            tariff: $tariff,
            id: $id ?? $scheduledTask->userId,
        );
    }

    private function makeUserRepository(?User $user = null): UserRepositoryInterface
    {
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($user);

        return $userRepository;
    }

    private function makeHandler(
        MessageBusInterface $commandBus,
        MessageBusInterface $eventBus,
         TaskRepositoryInterface $taskRepository,
         VideoRepositoryInterface $videoRepository,
         LogServiceInterface $logService,
         LockFactory $lockFactory,
         TaskCancellationTrigger $cancellationTrigger,
         TranscodeProcessService $transcodeProcessService,
         TranscodeTaskPreparationService $transcodeTaskPreparationService,
         TranscodeTaskFinalizationService $transcodeTaskFinalizationService,
         UserRepositoryInterface $userRepository,
     ): TranscodeVideoHandler {
         return new TranscodeVideoHandler(
             $commandBus,
             $eventBus,
             $taskRepository,
             $videoRepository,
             $logService,
             $lockFactory,
             $cancellationTrigger,
             $transcodeProcessService,
             $transcodeTaskPreparationService,
             $transcodeTaskFinalizationService,
             $userRepository,
             $this->createStub(\App\Application\Service\StorageRealtimeNotifierInterface::class),
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
            $lockFactory,
            new TaskCancellationTrigger(new ArrayAdapter()),
            $this->createStub(TranscodeProcessService::class),
            $this->createStub(TranscodeTaskPreparationService::class),
            $this->createStub(TranscodeTaskFinalizationService::class),
            $this->createStub(UserRepositoryInterface::class),
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
            $this->makeLockFactory(acquired: false),
            new TaskCancellationTrigger(new ArrayAdapter()),
            $this->createStub(TranscodeProcessService::class),
            $this->createStub(TranscodeTaskPreparationService::class),
            $this->createStub(TranscodeTaskFinalizationService::class),
            $this->createStub(UserRepositoryInterface::class),
        );

        $handler(new TranscodeVideo($this->makeScheduledTask()));

        $this->assertSame([TranscodeVideoStart::class, TranscodeVideoFail::class], $events);
    }

    public function testDispatchesFailAndThrowsErrorWhenVideoNotFound(): void
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
            $this->makeLockFactory(acquired: true, expectRelease: true),
            new TaskCancellationTrigger(new ArrayAdapter()),
            $this->createStub(TranscodeProcessService::class),
            $this->createStub(TranscodeTaskPreparationService::class),
            $this->createStub(TranscodeTaskFinalizationService::class),
            $this->createStub(UserRepositoryInterface::class),
        );

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Call to a member function id() on null');

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
            $this->makeLockFactory(acquired: true, expectRelease: true),
            $cancellationTrigger,
            $this->createStub(TranscodeProcessService::class),
            $this->createStub(TranscodeTaskPreparationService::class),
            $this->createStub(TranscodeTaskFinalizationService::class),
            $this->createStub(UserRepositoryInterface::class),
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
            $this->makeLockFactory(acquired: true, expectRelease: true),
            new TaskCancellationTrigger(new ArrayAdapter()),
            $this->createStub(TranscodeProcessService::class),
            $this->createStub(TranscodeTaskPreparationService::class),
            $this->createStub(TranscodeTaskFinalizationService::class),
            $this->createStub(UserRepositoryInterface::class),
        );

        $handler(new TranscodeVideo($this->makeScheduledTask()));

        $this->assertSame([TranscodeVideoStart::class, TranscodeVideoFail::class], $events);
    }

    public function testSuccessfulTranscoding(): void
    {
        $task = TaskFake::create();
        $video = $this->makeVideoWithDuration(size: 10 * 1024 * 1024);
        $report = $this->makeSuccessReport();
        $context = new TranscodeStartContextDTO($task, $video, new PresetFake(), 'output/path.mp4', '/abs/output/path.mp4', '/input/path.mp4', 0.0);

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRepository->method('findByIdFresh')->willReturn($task);

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $videoRepository->method('findById')->willReturn($video);

        $transcodeTaskPreparationService = $this->createMock(TranscodeTaskPreparationService::class);
        $transcodeTaskPreparationService->expects($this->once())->method('prepare')->with($task, $video, $this->anything())->willReturn($context);

        $transcodeProcessService = $this->createMock(TranscodeProcessService::class);
        $transcodeProcessService->expects($this->once())->method('run')->with($context)->willReturn($report);

        $transcodeTaskFinalizationService = $this->createMock(TranscodeTaskFinalizationService::class);
        $transcodeTaskFinalizationService->expects($this->once())->method('handleSuccess')->with($context, $report);
        $transcodeTaskFinalizationService->expects($this->never())->method('handleCancellation');
        $transcodeTaskFinalizationService->expects($this->never())->method('handleFailure');

        $events = [];
        $eventBus = $this->makeSpyBus($events);

        $commandBusDispatched = [];
        $commandBus = $this->makeSpyBus($commandBusDispatched);

        $user = $this->makeUser($this->makeTariff());

        $handler = $this->makeHandler(
            $commandBus,
            $eventBus,
            $taskRepository,
            $videoRepository,
            $this->createStub(LogServiceInterface::class),
            $this->makeLockFactory(acquired: true, expectRelease: true),
            new TaskCancellationTrigger(new ArrayAdapter()),
            $transcodeProcessService,
            $transcodeTaskPreparationService,
            $transcodeTaskFinalizationService,
            $this->makeUserRepository($user),
        );

        $handler(new TranscodeVideo($this->makeScheduledTask()));

        $this->assertSame([TranscodeVideoStart::class, TranscodeVideoSuccess::class], $events);
        $this->assertContains(StartTaskScheduler::class, $commandBusDispatched);
    }

    public function testTranscodingCancelledDuringProcess(): void
    {
        $task = TaskFake::create();
        $video = $this->makeVideoWithDuration(size: 10 * 1024 * 1024);
        $report = $this->makeCancelledReport();
        $context = new TranscodeStartContextDTO($task, $video, new PresetFake(), 'output/path.mp4', '/abs/output/path.mp4', '/input/path.mp4', 0.0);

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

        $user = $this->makeUser($this->makeTariff());

        $handler = $this->makeHandler(
            $commandBus,
            $eventBus,
            $taskRepository,
            $videoRepository,
            $this->createStub(LogServiceInterface::class),
            $this->makeLockFactory(acquired: true, expectRelease: true),
            new TaskCancellationTrigger(new ArrayAdapter()),
            $transcodeProcessService,
            $transcodeTaskPreparationService,
            $transcodeTaskFinalizationService,
            $this->makeUserRepository($user),
        );

        $handler(new TranscodeVideo($this->makeScheduledTask()));

        $this->assertSame([TranscodeVideoStart::class, TranscodeVideoFail::class], $events);
        $this->assertContains(StartTaskScheduler::class, $commandBusDispatched);
    }

    public function testLockReleaseExceptionIsLoggedAndSwallowed(): void
    {
        $task = TaskFake::create();
        $video = $this->makeVideoWithDuration(size: 10 * 1024 * 1024);
        $report = $this->makeSuccessReport();
        $context = new TranscodeStartContextDTO($task, $video, new PresetFake(), 'output/path.mp4', '/abs/output/path.mp4', '/input/path.mp4', 0.0);

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

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->once())->method('log')->with(
            'task',
            'transcode',
            $this->anything(),
            LogLevel::ERROR,
            'Failed to release transcode task mutex',
            $this->arrayHasKey('message'),
        );

        $events = [];
        $eventBus = $this->makeSpyBus($events);

        $commandBusDispatched = [];
        $commandBus = $this->makeSpyBus($commandBusDispatched);

        $user = $this->makeUser($this->makeTariff());

        $handler = $this->makeHandler(
            $commandBus,
            $eventBus,
            $taskRepository,
            $videoRepository,
            $logService,
            $lockFactory,
            new TaskCancellationTrigger(new ArrayAdapter()),
            $transcodeProcessService,
            $transcodeTaskPreparationService,
            $transcodeTaskFinalizationService,
            $this->makeUserRepository($user),
        );

        // Exception from lock release must be swallowed — no exception propagated
        $handler(new TranscodeVideo($this->makeScheduledTask()));

        $this->assertSame([TranscodeVideoStart::class, TranscodeVideoSuccess::class], $events);
        $this->assertContains(StartTaskScheduler::class, $commandBusDispatched);
    }

    public function testTranscodingExceptionDispatchesFailAndRethrows(): void
    {
        $task = TaskFake::create();
        $video = $this->makeVideoWithDuration(size: 10 * 1024 * 1024);
        $context = new TranscodeStartContextDTO($task, $video, new PresetFake(), 'output/path.mp4', '/abs/output/path.mp4', '/input/path.mp4', 0.0);

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRepository->method('findByIdFresh')->willReturn($task);

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $videoRepository->method('findById')->willReturn($video);

        $transcodeTaskPreparationService = $this->createMock(TranscodeTaskPreparationService::class);
        $transcodeTaskPreparationService->expects($this->once())->method('prepare')->willReturn($context);

        $transcodeProcessService = $this->createMock(TranscodeProcessService::class);
        $transcodeProcessService->expects($this->once())->method('run')->willThrowException(new \RuntimeException('ffmpeg process failed'));

        $transcodeTaskFinalizationService = $this->createMock(TranscodeTaskFinalizationService::class);
        $transcodeTaskFinalizationService->expects($this->once())->method('handleFailure')->with($task, $this->isInstanceOf(\RuntimeException::class), $context->absoluteOutputPath);
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
            $this->makeLockFactory(acquired: true, expectRelease: true),
            new TaskCancellationTrigger(new ArrayAdapter()),
            $transcodeProcessService,
            $transcodeTaskPreparationService,
            $transcodeTaskFinalizationService,
            $this->makeUserRepository($this->makeUser($this->makeTariff())),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ffmpeg process failed');

        try {
            $handler(new TranscodeVideo($this->makeScheduledTask()));
        } finally {
            $this->assertSame([TranscodeVideoStart::class, TranscodeVideoFail::class], $events);
        }
    }

    public function testThrowsUserNotFoundWhenQuotaCheckCannotResolveUser(): void
    {
        $task = TaskFake::create();

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRepository->method('findByIdFresh')->willReturn($task);

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $videoRepository->method('findById')->willReturn($this->makeVideoWithDuration(size: 10 * 1024 * 1024));

        $events = [];
        $eventBus = $this->makeSpyBus($events);

        $handler = $this->makeHandler(
            $this->createStub(MessageBusInterface::class),
            $eventBus,
            $taskRepository,
            $videoRepository,
            $this->createStub(LogServiceInterface::class),
            $this->makeLockFactory(acquired: true, expectRelease: true),
            new TaskCancellationTrigger(new ArrayAdapter()),
            $this->createStub(TranscodeProcessService::class),
            $this->createStub(TranscodeTaskPreparationService::class),
            $this->createStub(TranscodeTaskFinalizationService::class),
            $this->makeUserRepository(),
        );

        $this->expectException(UserNotFound::class);

        try {
            $handler(new TranscodeVideo($this->makeScheduledTask()));
        } finally {
            $this->assertSame([TranscodeVideoStart::class, TranscodeVideoFail::class], $events);
        }
    }

    public function testThrowsTariffNotFoundWhenQuotaCheckUserHasNoTariff(): void
    {
        $task = TaskFake::create();

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRepository->method('findByIdFresh')->willReturn($task);

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $videoRepository->method('findById')->willReturn($this->makeVideoWithDuration(size: 10 * 1024 * 1024));

        $events = [];
        $eventBus = $this->makeSpyBus($events);

        $handler = $this->makeHandler(
            $this->createStub(MessageBusInterface::class),
            $eventBus,
            $taskRepository,
            $videoRepository,
            $this->createStub(LogServiceInterface::class),
            $this->makeLockFactory(acquired: true, expectRelease: true),
            new TaskCancellationTrigger(new ArrayAdapter()),
            $this->createStub(TranscodeProcessService::class),
            $this->createStub(TranscodeTaskPreparationService::class),
            $this->createStub(TranscodeTaskFinalizationService::class),
            $this->makeUserRepository($this->makeUser()),
        );

        $this->expectException(TariffNotFound::class);

        try {
            $handler(new TranscodeVideo($this->makeScheduledTask()));
        } finally {
            $this->assertSame([TranscodeVideoStart::class, TranscodeVideoFail::class], $events);
        }
    }

    public function testThrowsStorageSizeExceedsQuotaBeforePreparingTranscode(): void
    {
        $task = TaskFake::create();
        $video = $this->makeVideoWithDuration(size: 2 * 1024 * 1024);

        $taskRepository = $this->createStub(TaskRepositoryInterface::class);
        $taskRepository->method('findByIdFresh')->willReturn($task);
        $taskRepository->method('getStorageSize')->willReturn(0);

        $videoRepository = $this->createStub(VideoRepositoryInterface::class);
        $videoRepository->method('findById')->willReturn($video);
        $videoRepository->method('getStorageSize')->willReturn(0);

        $transcodeTaskPreparationService = $this->createMock(TranscodeTaskPreparationService::class);
        $transcodeTaskPreparationService->expects($this->never())->method('prepare');

        $events = [];
        $eventBus = $this->makeSpyBus($events);

        $handler = $this->makeHandler(
            $this->createStub(MessageBusInterface::class),
            $eventBus,
            $taskRepository,
            $videoRepository,
            $this->createStub(LogServiceInterface::class),
            $this->makeLockFactory(acquired: true, expectRelease: true),
            new TaskCancellationTrigger(new ArrayAdapter()),
            $this->createStub(TranscodeProcessService::class),
            $transcodeTaskPreparationService,
            $this->createStub(TranscodeTaskFinalizationService::class),
            $this->makeUserRepository($this->makeUser($this->makeTariff(0.001))),
        );

        $this->expectException(StorageSizeExceedsQuota::class);

        try {
            $handler(new TranscodeVideo($this->makeScheduledTask()));
        } finally {
            $this->assertSame([TranscodeVideoStart::class, TranscodeVideoFail::class], $events);
        }
    }
}
