<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\TariffMaxHeight;
use PHPUnit\Framework\TestCase;

final class TariffMaxHeightTest extends TestCase
{
    public function testCreatesValidHeight(): void
    {
        $vo = new TariffMaxHeight(1080);

        $this->assertSame(1080, $vo->value());
    }

    public function testThrowsOnZero(): void
    {
        $this->expectException(\DomainException::class);

        new TariffMaxHeight(0);
    }

    public function testThrowsOnNegative(): void
    {
        $this->expectException(\DomainException::class);

        new TariffMaxHeight(-1);
    }

    public function testEquals(): void
    {
        $a = new TariffMaxHeight(720);
        $b = new TariffMaxHeight(720);
        $c = new TariffMaxHeight(1080);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
