<?php

namespace App\Tests\Domain\Entity;

use App\Domain\Video\Entity\Task;
use App\Domain\Video\ValueObject\Progress;
use App\Domain\Video\ValueObject\TaskStatus;
use Random\RandomException;
use Symfony\Component\Uid\Uuid;

class TaskFake extends Task
{
    /**
     * @throws RandomException
     */
    public function __construct()
    {
        $video = new VideoFake();
        $preset = new PresetFake();
        parent::__construct(
            videoId: $video->id() ?? Uuid::v4(),
            presetId: $preset->id() ?? random_int(1, 1000),
            userId: $video->userId(),
            status: TaskStatus::pending(),
            progress: new Progress(0)
        );
    }
}
