<?php
declare(strict_types=1);

namespace App\Application\Service\Storage;

use App\Domain\Shared\ValueObject\Uuid;

interface StorageRealtimeNotifierInterface
{
    // todo интерфейс-то круто. Но тут он зачем? у таск и видео notifier их нет
    public function notifyStorageUpdated(Uuid $userId): void;
}
