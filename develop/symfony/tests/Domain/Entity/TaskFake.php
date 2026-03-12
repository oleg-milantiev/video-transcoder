<?php

namespace App\Tests\Domain\Entity;

use App\Domain\Video\Entity\Task;

class TaskFake extends Task
{
    public function __construct()
    {
        $video = new VideoFake();
        $preset = new PresetFake();
        parent::__construct($video, $preset);
    }
}

