<?php

declare(strict_types=1);

namespace App\Tests\Application\QueryHandler;

use App\Application\Command\Task\StartTaskScheduler;
use App\Application\Event\StartTranscodeFail;
use App\Application\Event\StartTranscodeStart;
use App\Application\Event\StartTranscodeSuccess;
use App\Application\Exception\VideoNotFoundException;
use App\Application\Logging\LogServiceInterface;
use App\Application\Query\StartTranscodeQuery;
use App\Application\QueryHandler\StartTranscodeHandler;
use App\Application\DTO\TaskItemDTO;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\Video\Entity\Preset;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Repository\PresetRepositoryInterface;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\ValueObject\Bitrate;
use App\Domain\Video\ValueObject\Codec;
use App\Domain\Video\ValueObject\PresetTitle;
use App\Domain\Video\ValueObject\Resolution;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Video\ValueObject\VideoTitle;
use App\Domain\Video\ValueObject\FileExtension;
use App\Infrastructure\Security\Voter\VideoAccessVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use App\Domain\Shared\ValueObject\Uuid;

class StartTranscodeHandlerTest extends TestCase
{
    /**
     * @throws ExceptionInterface
     */
    public function testCreatesTaskAndDispatchesScheduler(): void
    {
        $videoId = Uuid::fromString('123e4567-e89b-42d3-a456-426614174100');
        $video = Video::reconstitute(
            new VideoTitle('Source Clip'),
            new FileExtension('mp4'),
            userId: Uuid::fromString('123e4567-e89b-42d3-a456-426614174077'),
            meta: [],
            dates: VideoDates::create(new \DateTimeImmutable('2026-03-18 12:00:00')),
            id: $videoId,
        );

        $preset = new Preset(
            new PresetTitle('HD 720p'),
            new Resolution(1280, 720),
            new Codec('h264'),
            new Bitrate(50.0),
            id: Uuid::fromString('123e4567-e89b-42d3-a456-426614174005'),
        );

        $user = new User('user@example.com', ['ROLE_USER'], id: $video->userId());

        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(StartTaskScheduler::class))
            ->willReturn(new Envelope(new StartTaskScheduler(), [new HandledStamp(null, 'handler')]));

        $eventBus = $this->createMock(MessageBusInterface::class);
        $dispatchedEventClasses = [];
        $eventBus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use (&$dispatchedEventClasses): Envelope {
                $dispatchedEventClasses[] = $message::class;

                return new Envelope($message);
            });

        $videoRepo = $this->createMock(VideoRepositoryInterface::class);
        $videoRepo->expects($this->once())
            ->method('findById')
            ->with($videoId)
            ->willReturn($video);

        $presetRepo = $this->createMock(PresetRepositoryInterface::class);
        $presetRepo->expects($this->once())
            ->method('findById')
            ->with($preset->id())
            ->willReturn($preset);

        $taskRepo = $this->createMock(TaskRepositoryInterface::class);
        $taskRepo->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Task::class))
            ->willReturnCallback(static function (Task $task): void {
                $task->assignId(Uuid::fromString('123e4567-e89b-42d3-a456-426614174321'));
            });

        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->expects($this->once())
            ->method('findById')
            ->with($user->id())
            ->willReturn($user);

        $security = $this->createMock(Security::class);
        $security->expects($this->once())
            ->method('isGranted')
            ->with(VideoAccessVoter::CAN_START_TRANSCODE, $video)
            ->willReturn(true);

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->exactly(3))->method('log');

        $handler = new StartTranscodeHandler($commandBus, $eventBus, $videoRepo, $presetRepo, $taskRepo, $userRepo, $logService, $security);
        $query = new StartTranscodeQuery($videoId->toRfc4122(), $preset->id()->toRfc4122(), $user->id()->toRfc4122());
        $dto = $handler($query);

        $this->assertInstanceOf(TaskItemDTO::class, $dto);
        $this->assertSame('Source Clip', $dto->videoTitle);
        $this->assertSame('HD 720p', $dto->presetTitle);
        $this->assertSame('PENDING', $dto->status);
        $this->assertSame([
            StartTranscodeStart::class,
            StartTranscodeSuccess::class,
        ], $dispatchedEventClasses);
    }

    /**
     * @throws ExceptionInterface
     */
    public function testThrowsWhenVideoNotFound(): void
    {
        $commandBus = $this->createStub(MessageBusInterface::class);
        $eventBus = $this->createMock(MessageBusInterface::class);
        $dispatchedEventClasses = [];
        $eventBus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use (&$dispatchedEventClasses): Envelope {
                $dispatchedEventClasses[] = $message::class;

                return new Envelope($message);
            });
        $videoRepo = $this->createStub(VideoRepositoryInterface::class);
        $videoRepo->method('findById')->willReturn(null);

        $handler = new StartTranscodeHandler(
            $commandBus,
            $eventBus,
            $videoRepo,
            $this->createStub(PresetRepositoryInterface::class),
            $this->createStub(TaskRepositoryInterface::class),
            $this->createStub(UserRepositoryInterface::class),
            $this->createStub(LogServiceInterface::class),
            $this->createStub(Security::class),
        );

        $this->expectException(VideoNotFoundException::class);
        try {
            $handler(new StartTranscodeQuery(
                '123e4567-e89b-42d3-a456-426614174101',
                '123e4567-e89b-42d3-a456-426614174001',
                '123e4567-e89b-42d3-a456-426614174001'
            ));
        } finally {
            $this->assertSame([
                StartTranscodeStart::class,
                StartTranscodeFail::class,
            ], $dispatchedEventClasses);
        }
    }
}

