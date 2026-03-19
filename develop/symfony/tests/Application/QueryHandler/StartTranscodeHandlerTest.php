<?php

declare(strict_types=1);

namespace App\Tests\Application\QueryHandler;

use App\Application\Command\Task\StartTaskScheduler;
use App\Application\Exception\QueryException;
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
use App\Domain\Video\ValueObject\VideoStatus;
use App\Domain\Video\ValueObject\VideoTitle;
use App\Domain\Video\ValueObject\FileExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Uid\UuidV4;

class StartTranscodeHandlerTest extends TestCase
{
    /**
     * @throws ExceptionInterface
     */
    public function testCreatesTaskAndDispatchesScheduler(): void
    {
        $videoId = UuidV4::fromString('123e4567-e89b-42d3-a456-426614174100');
        $video = new Video(
            new VideoTitle('Source Clip'),
            new FileExtension('mp4'),
            VideoStatus::UPLOADED,
            userId: 77,
            createdAt: new \DateTimeImmutable('2026-03-18 12:00:00'),
            meta: [],
            id: $videoId,
        );

        $preset = new Preset(
            new PresetTitle('HD 720p'),
            new Resolution(1280, 720),
            new Codec('h264'),
            new Bitrate(50.0),
            id: 5,
        );

        $user = new User('user@example.com', ['ROLE_USER'], id: $video->userId());

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(StartTaskScheduler::class))
            ->willReturn(new Envelope(new StartTaskScheduler(), [new HandledStamp(null, 'handler')]));

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
                $task->setId(321);
            });

        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->expects($this->once())
            ->method('findById')
            ->with($user->id())
            ->willReturn($user);

        $handler = new StartTranscodeHandler($messageBus, $videoRepo, $presetRepo, $taskRepo, $userRepo);
        $query = new StartTranscodeQuery($videoId->toRfc4122(), $preset->id(), $user->id());
        $dto = $handler($query);

        $this->assertInstanceOf(TaskItemDTO::class, $dto);
        $this->assertSame('Source Clip', $dto->videoTitle);
        $this->assertSame('HD 720p', $dto->presetTitle);
        $this->assertSame('PENDING', $dto->status);
    }

    /**
     * @throws ExceptionInterface
     */
    public function testThrowsWhenVideoNotFound(): void
    {
        $messageBus = $this->createStub(MessageBusInterface::class);
        $videoRepo = $this->createStub(VideoRepositoryInterface::class);
        $videoRepo->method('findById')->willReturn(null);

        $handler = new StartTranscodeHandler(
            $messageBus,
            $videoRepo,
            $this->createStub(PresetRepositoryInterface::class),
            $this->createStub(TaskRepositoryInterface::class),
            $this->createStub(UserRepositoryInterface::class),
        );

        $this->expectException(QueryException::class);
        $handler(new StartTranscodeQuery('123e4567-e89b-42d3-a456-426614174101', 1, 1));
    }
}

