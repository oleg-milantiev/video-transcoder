<?php

declare(strict_types=1);

namespace App\Tests\Application\Factory;

use App\Application\Factory\FlashNotificationFactory;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\ValueObject\FileExtension;
use App\Domain\Video\ValueObject\VideoDates;
use App\Domain\Video\ValueObject\VideoTitle;
use PHPUnit\Framework\TestCase;
use App\Domain\Shared\ValueObject\Uuid;

final class FlashNotificationFactoryTest extends TestCase
{
    public function testBuildsUploadNotification(): void
    {
        $factory = new FlashNotificationFactory();
        $video = Video::reconstitute(
            title: new VideoTitle('Clip'),
            extension: new FileExtension('mp4'),
            userId: Uuid::fromString('123e4567-e89b-42d3-a456-426614174501'),
            meta: [],
            dates: VideoDates::create(),
            id: Uuid::fromString('123e4567-e89b-42d3-a456-426614174502'),
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

    public function testBuildsTranscodeStartedNotification(): void
    {
        $factory = new FlashNotificationFactory();
        $task = $this->createTask();

        $dto = $factory->transcodeStarted($task);
        $payload = $dto->toArray();

        $this->assertSame('info', $payload['level']);
        $this->assertSame('Transcoding started', $payload['title']);
        $this->assertStringContainsString('/video/123e4567-e89b-42d3-a456-426614174503', $payload['html']);
        $this->assertSame(5000, $payload['timer']);
    }

    public function testBuildsTranscodeCompletedNotification(): void
    {
        $factory = new FlashNotificationFactory();
        $task = $this->createTask();

        $dto = $factory->transcodeCompleted($task);
        $payload = $dto->toArray();

        $this->assertSame('success', $payload['level']);
        $this->assertSame('Transcoding completed', $payload['title']);
        $this->assertStringContainsString('/task/123e4567-e89b-42d3-a456-426614174506/download', $payload['html']);
        $this->assertStringContainsString('/video/123e4567-e89b-42d3-a456-426614174503', $payload['html']);
        $this->assertSame(10000, $payload['timer']);
    }

    private function createTask(): Task
    {
        $task = Task::create(
            Uuid::fromString('123e4567-e89b-42d3-a456-426614174503'),
            Uuid::fromString('123e4567-e89b-42d3-a456-426614174504'),
            Uuid::fromString('123e4567-e89b-42d3-a456-426614174505'),
        );
        $task->assignId(Uuid::fromString('123e4567-e89b-42d3-a456-426614174506'));

        return $task;
    }
}

