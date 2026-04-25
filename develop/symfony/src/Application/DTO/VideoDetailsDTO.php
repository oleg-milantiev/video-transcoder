<?php
declare(strict_types=1);

namespace App\Application\DTO;

readonly class VideoDetailsDTO
{
    private function __construct(
        public VideoItemDTO $video,
        /** @var PresetItemDTO[] */
        public array $presets,
        /** @var TaskItemDTO[] */
        public array $tasks,
    ) {
    }

    /**
     * @param PresetItemDTO[] $presets
     * @param TaskItemDTO[] $tasks
     */
    public static function create(VideoItemDTO $video, array $presets, array $tasks): self
    {
        return new self($video, $presets, $tasks);
    }
}
