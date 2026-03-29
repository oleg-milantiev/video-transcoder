<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\Entity;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\User\Entity\Tariff;
use App\Domain\User\ValueObject\TariffDelay;
use App\Domain\User\ValueObject\TariffInstance;
use App\Domain\User\ValueObject\TariffMaxHeight;
use App\Domain\User\ValueObject\TariffMaxWidth;
use App\Domain\User\ValueObject\TariffStorageGb;
use App\Domain\User\ValueObject\TariffStorageHour;
use App\Domain\User\ValueObject\TariffTitle;
use App\Domain\User\ValueObject\TariffVideoDuration;
use App\Domain\User\ValueObject\TariffVideoSize;
use PHPUnit\Framework\TestCase;

final class TariffTest extends TestCase
{
    public function testConstructsWithAllFields(): void
    {
        $id = Uuid::fromString('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa');
        $tariff = new Tariff(
            new TariffTitle('Pro'),
            new TariffDelay(60),
            new TariffInstance(3),
            new TariffVideoDuration(3600),
            new TariffVideoSize(500.0),
            new TariffMaxWidth(1920),
            new TariffMaxHeight(1080),
            new TariffStorageGb(100.0),
            new TariffStorageHour(24),
            $id,
        );

        $this->assertSame($id, $tariff->id());
        $this->assertSame('Pro', $tariff->title()->value());
        $this->assertSame(60, $tariff->delay()->value());
        $this->assertSame(3, $tariff->instance()->value());
        $this->assertSame(3600, $tariff->videoDuration()->value());
        $this->assertSame(500.0, $tariff->videoSize()->value());
        $this->assertSame(1920, $tariff->maxWidth()->value());
        $this->assertSame(1080, $tariff->maxHeight()->value());
        $this->assertSame(100.0, $tariff->storageGb()->value());
        $this->assertSame(24, $tariff->storageHour()->value());
        $this->assertSame('Pro', (string) $tariff);
    }

    public function testConstructsWithoutId(): void
    {
        $tariff = new Tariff(
            new TariffTitle('Free'),
            new TariffDelay(0),
            new TariffInstance(1),
            new TariffVideoDuration(60),
            new TariffVideoSize(10.0),
            new TariffMaxWidth(640),
            new TariffMaxHeight(480),
            new TariffStorageGb(1.0),
            new TariffStorageHour(1),
        );

        $this->assertNull($tariff->id());
        $this->assertSame('Free', $tariff->title()->value());
        $this->assertSame(0, $tariff->delay()->value());
        $this->assertSame(1, $tariff->instance()->value());
        $this->assertSame(60, $tariff->videoDuration()->value());
        $this->assertSame(10.0, $tariff->videoSize()->value());
        $this->assertSame(640, $tariff->maxWidth()->value());
        $this->assertSame(480, $tariff->maxHeight()->value());
        $this->assertSame(1.0, $tariff->storageGb()->value());
        $this->assertSame(1, $tariff->storageHour()->value());
        $this->assertSame('Free', (string) $tariff);
    }
}
