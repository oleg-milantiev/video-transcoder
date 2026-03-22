<?php

namespace App\Tests\Domain\Entity;

use App\Domain\Video\Entity\Task;
use App\Domain\Video\ValueObject\Progress;
use App\Domain\Video\ValueObject\TaskDates;
use App\Domain\Video\ValueObject\TaskStatus;
use Symfony\Component\Uid\UuidV4;

final class TaskFake
{
    public static function create(): Task
    {
        $video = VideoFake::create();
        $preset = new PresetFake();

        return Task::reconstitute(
            videoId: $video->id() ?? UuidV4::v4(),
            presetId: $preset->id() ?? UuidV4::v4(),
            userId: $video->userId(),
            status: TaskStatus::pending(),
            progress: new Progress(0),
            dates: TaskDates::create(),
            id: UuidV4::v4(),
        );
    }
}
