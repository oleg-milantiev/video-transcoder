<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\TariffVideoSize;
use PHPUnit\Framework\TestCase;

final class TariffVideoSizeTest extends TestCase
{
    public function testCreatesValidSize(): void
    {
        $vo = new TariffVideoSize(500.0);

        $this->assertSame(500.0, $vo->value());
    }

    public function testThrowsOnZero(): void
    {
        $this->expectException(\DomainException::class);

        new TariffVideoSize(0.0);
    }

    public function testThrowsOnNegative(): void
    {
        $this->expectException(\DomainException::class);

        new TariffVideoSize(-1.0);
    }

    public function testEquals(): void
    {
        $a = new TariffVideoSize(100.0);
        $b = new TariffVideoSize(100.0);
        $c = new TariffVideoSize(200.0);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
