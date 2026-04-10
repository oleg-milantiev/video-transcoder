<?php
declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Shared\ValueObject\Uuid;

interface StorageRealtimeNotifierInterface
{
    public function notifyStorageUpdated(Uuid $userId): void;
}
