<?php

namespace App\Domain\Video\Repository;

use App\Domain\Video\Entity\Preset;
use Symfony\Component\Uid\UuidV4 as Uuid;

interface PresetRepositoryInterface
{
    public function save(Preset $preset): void;
    public function findById(Uuid $id): ?Preset;
    public function log(Uuid $id, string $level, string $text): void;
}
