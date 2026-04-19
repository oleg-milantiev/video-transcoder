<?php

declare(strict_types=1);

namespace App\Tests\Application\DTO;

use App\Application\DTO\PresetItemDTO;
use App\Application\DTO\TaskItemDTO;
use App\Application\DTO\VideoDetailsDTO;
use App\Application\DTO\VideoItemDTO;
use PHPUnit\Framework\TestCase;

class VideoDetailsDTOTest extends TestCase
{
    public function testCreateStoresAllFields(): void
    {
        $videoDto = $this->createStub(VideoItemDTO::class);
        $presetDto = $this->createStub(PresetItemDTO::class);
        $taskDto = $this->createStub(TaskItemDTO::class);

        $dto = VideoDetailsDTO::create($videoDto, [$presetDto], [$taskDto]);

        $this->assertSame($videoDto, $dto->video);
        $this->assertSame([$presetDto], $dto->presets);
        $this->assertSame([$taskDto], $dto->tasks);
    }

    public function testCreateWithEmptyPresetsAndTasks(): void
    {
        $videoDto = $this->createStub(VideoItemDTO::class);

        $dto = VideoDetailsDTO::create($videoDto, [], []);

        $this->assertSame($videoDto, $dto->video);
        $this->assertSame([], $dto->presets);
        $this->assertSame([], $dto->tasks);
    }
}
