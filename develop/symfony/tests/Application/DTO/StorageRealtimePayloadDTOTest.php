<?php

declare(strict_types=1);

namespace App\Tests\Application\DTO;

use App\Application\DTO\StorageRealtimePayloadDTO;
use PHPUnit\Framework\TestCase;

final class StorageRealtimePayloadDTOTest extends TestCase
{
    public function testFromSizesAndToArray(): void
    {
        $dto = StorageRealtimePayloadDTO::fromSizes(500, 2000);

        $this->assertSame(500, $dto->storageNow);
        $this->assertSame(2000, $dto->storageMax);

        $array = $dto->toArray();
        $this->assertSame(['storageNow' => 500, 'storageMax' => 2000], $array);
    }

    public function testFromSizesWithZeroValues(): void
    {
        $dto = StorageRealtimePayloadDTO::fromSizes(0, 0);

        $this->assertSame(0, $dto->storageNow);
        $this->assertSame(0, $dto->storageMax);
    }
}
