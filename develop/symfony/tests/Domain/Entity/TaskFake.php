<?php

namespace App\Tests\Domain\Entity;

use App\Domain\Video\Entity\Task;
use App\Domain\Video\ValueObject\Progress;
use App\Domain\Video\ValueObject\TaskStatus;
use Symfony\Component\Uid\UuidV4;

class TaskFake extends Task
{
    public function __construct()
    {
        $video = new VideoFake();
        $preset = new PresetFake();
        parent::__construct(
            videoId: $video->id() ?? UuidV4::v4(),
            presetId: $preset->id() ?? UuidV4::v4(),
            userId: $video->userId(),
            status: TaskStatus::pending(),
            progress: new Progress(0),
            id: UuidV4::v4(),
        );
    }
}
