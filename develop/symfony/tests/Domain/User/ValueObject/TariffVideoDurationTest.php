<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\TariffVideoDuration;
use PHPUnit\Framework\TestCase;

final class TariffVideoDurationTest extends TestCase
{
    public function testCreatesValidDuration(): void
    {
        $vo = new TariffVideoDuration(3600);

        $this->assertSame(3600, $vo->value());
    }

    public function testThrowsOnZero(): void
    {
        $this->expectException(\DomainException::class);

        new TariffVideoDuration(0);
    }

    public function testThrowsOnNegative(): void
    {
        $this->expectException(\DomainException::class);

        new TariffVideoDuration(-1);
    }

    public function testEquals(): void
    {
        $a = new TariffVideoDuration(60);
        $b = new TariffVideoDuration(60);
        $c = new TariffVideoDuration(120);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
