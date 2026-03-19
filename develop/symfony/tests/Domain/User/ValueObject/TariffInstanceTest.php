<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\TariffInstance;
use PHPUnit\Framework\TestCase;

final class TariffInstanceTest extends TestCase
{
    public function testCreatesValidInstanceCount(): void
    {
        $instance = new TariffInstance(1);

        $this->assertSame(1, $instance->value());
    }

    public function testThrowsOnZeroOrNegativeInstanceCount(): void
    {
        $this->expectException(\DomainException::class);

        new TariffInstance(0);
    }
}

