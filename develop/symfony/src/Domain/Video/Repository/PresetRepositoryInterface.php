<?php
declare(strict_types=1);

namespace App\Domain\Video\Repository;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Entity\Preset;

interface PresetRepositoryInterface
{
    public function save(Preset $preset): void;
    public function findById(Uuid $id): ?Preset;
    public function log(Uuid $id, string $level, string $text): void;
}
