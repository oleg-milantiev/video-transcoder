<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\TariffStorageHour;
use PHPUnit\Framework\TestCase;

final class TariffStorageHourTest extends TestCase
{
    public function testCreatesValidHour(): void
    {
        $vo = new TariffStorageHour(24);

        $this->assertSame(24, $vo->value());
    }

    public function testThrowsOnZero(): void
    {
        $this->expectException(\DomainException::class);

        new TariffStorageHour(0);
    }

    public function testThrowsOnNegative(): void
    {
        $this->expectException(\DomainException::class);

        new TariffStorageHour(-1);
    }

    public function testEquals(): void
    {
        $a = new TariffStorageHour(12);
        $b = new TariffStorageHour(12);
        $c = new TariffStorageHour(24);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
