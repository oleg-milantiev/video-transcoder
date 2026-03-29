<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\TariffMaxWidth;
use PHPUnit\Framework\TestCase;

final class TariffMaxWidthTest extends TestCase
{
    public function testCreatesValidWidth(): void
    {
        $vo = new TariffMaxWidth(1920);

        $this->assertSame(1920, $vo->value());
    }

    public function testThrowsOnZero(): void
    {
        $this->expectException(\DomainException::class);

        new TariffMaxWidth(0);
    }

    public function testThrowsOnNegative(): void
    {
        $this->expectException(\DomainException::class);

        new TariffMaxWidth(-1);
    }

    public function testEquals(): void
    {
        $a = new TariffMaxWidth(1280);
        $b = new TariffMaxWidth(1280);
        $c = new TariffMaxWidth(1920);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
