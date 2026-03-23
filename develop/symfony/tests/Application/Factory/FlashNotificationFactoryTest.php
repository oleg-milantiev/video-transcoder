<?php

declare(strict_types=1);

namespace App\Tests\Application\Factory;

use App\Application\Factory\FlashNotificationFactory;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Video\ValueObject\VideoStatus;
use App\Domain\Video\ValueObject\VideoTitle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV4;

final class FlashNotificationFactoryTest extends TestCase
{
    public function testBuildsUploadNotification(): void
    {
        $factory = new FlashNotificationFactory();
        $video = Video::reconstitute(
            title: new VideoTitle('Clip'),
            extension: new FileExtension('mp4'),
            status: VideoStatus::UPLOADED,
            userId: UuidV4::fromString('123e4567-e89b-42d3-a456-426614174501'),
            meta: [],
            dates: VideoDates::create(),
            id: UuidV4::fromString('123e4567-e89b-42d3-a456-426614174502'),
        );

        $dto = $factory->uploadCompleted($video);
        $payload = $dto->toArray();

        $this->assertSame('success', $payload['level']);
        $this->assertStringContainsString('/video/123e4567-e89b-42d3-a456-426614174502', $payload['html']);
    }

    public function testBuildsTranscodeFailureNotificationWithEscapedMessage(): void
    {
        $factory = new FlashNotificationFactory();
        $task = $this->createTask();

        $dto = $factory->transcodeFailed($task, new \RuntimeException('<boom>'));
        $payload = $dto->toArray();

        $this->assertSame('error', $payload['level']);
        $this->assertStringContainsString('&lt;boom&gt;', $payload['html']);
    }

    private function createTask(): Task
    {
        $task = Task::create(
            UuidV4::fromString('123e4567-e89b-42d3-a456-426614174503'),
            UuidV4::fromString('123e4567-e89b-42d3-a456-426614174504'),
            UuidV4::fromString('123e4567-e89b-42d3-a456-426614174505'),
        );
        $task->assignId(UuidV4::fromString('123e4567-e89b-42d3-a456-426614174506'));

        return $task;
    }
}

