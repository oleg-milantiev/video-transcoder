<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\Entity;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\User\Entity\Tariff;
use App\Domain\User\ValueObject\TariffDelay;
use App\Domain\User\ValueObject\TariffInstance;
use App\Domain\User\ValueObject\TariffTitle;
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
            $id,
        );

        $this->assertSame($id, $tariff->id());
        $this->assertSame('Pro', $tariff->title()->value());
        $this->assertSame(60, $tariff->delay()->value());
        $this->assertSame(3, $tariff->instance()->value());
        $this->assertSame('Pro', (string) $tariff);
    }

    public function testConstructsWithoutId(): void
    {
        $tariff = new Tariff(
            new TariffTitle('Free'),
            new TariffDelay(0),
            new TariffInstance(1),
        );

        $this->assertNull($tariff->id());
        $this->assertSame('Free', $tariff->title()->value());
        $this->assertSame(0, $tariff->delay()->value());
        $this->assertSame(1, $tariff->instance()->value());
        $this->assertSame('Free', (string) $tariff);
    }
}
