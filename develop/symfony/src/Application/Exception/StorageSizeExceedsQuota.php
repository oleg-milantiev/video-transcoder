<?php
declare(strict_types=1);

namespace App\Application\Exception;

final class StorageSizeExceedsQuota extends QueryException
{
    public static function create(float $fileSizeMb, float $storageNowMb, float $storageCapacityMb): self
    {
        return new self(sprintf('File size %.1f Mb upload blocked. Storage size (%.2f / %.2f Gb) exceeds your tariff limit.', $fileSizeMb, $storageNowMb / 1024, $storageCapacityMb / 1024));
    }
}
