<?php

namespace App\Domain\Video\Repository;

use App\Domain\Video\Entity\Preset;

interface PresetRepositoryInterface
{
    public function save(Preset $preset): void;
    public function findById(int $id): ?Preset;
    public function log(int $id, string $level, string $text): void;
}
