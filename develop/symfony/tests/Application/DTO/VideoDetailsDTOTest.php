<?php

declare(strict_types=1);

namespace App\Tests\Application\DTO;

use App\Application\DTO\PresetItemDTO;
use App\Application\DTO\TaskItemDTO;
use App\Application\DTO\VideoDetailsDTO;
use App\Application\DTO\VideoItemDTO;
use App\Application\Response\VideoListResponse;
use PHPUnit\Framework\TestCase;

class VideoDetailsDTOTest extends TestCase
{
    private function makeVideoList(): VideoListResponse
    {
        return new VideoListResponse([], 0, 1, 10, 1);
    }

    public function testCreateStoresAllFields(): void
    {
        $videoDto = $this->createStub(VideoItemDTO::class);
        $presetDto = $this->createStub(PresetItemDTO::class);
        $taskDto = $this->createStub(TaskItemDTO::class);
        $videoList = $this->makeVideoList();

        $dto = VideoDetailsDTO::create($videoDto, [$presetDto], [$taskDto], $videoList);

        $this->assertSame($videoDto, $dto->video);
        $this->assertSame([$presetDto], $dto->presets);
        $this->assertSame([$taskDto], $dto->tasks);
        $this->assertSame($videoList, $dto->videoList);
    }

    public function testCreateWithEmptyPresetsAndTasks(): void
    {
        $videoDto = $this->createStub(VideoItemDTO::class);
        $videoList = $this->makeVideoList();

        $dto = VideoDetailsDTO::create($videoDto, [], [], $videoList);

        $this->assertSame($videoDto, $dto->video);
        $this->assertSame([], $dto->presets);
        $this->assertSame([], $dto->tasks);
        $this->assertSame($videoList, $dto->videoList);
    }
}
