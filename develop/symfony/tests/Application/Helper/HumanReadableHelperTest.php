<?php
declare(strict_types=1);

namespace App\Tests\Application\Helper;

use PHPUnit\Framework\TestCase;
use App\Application\Helper\HumanReadableHelper;

final class HumanReadableHelperTest extends TestCase
{
    public function testExpiredWhenDateBeforeNow(): void
    {
        $now = new \DateTimeImmutable('2026-03-31 12:00:00');
        $date = $now->modify('-1 second');

        $this->assertSame('expired', HumanReadableHelper::formatDateExpired($date, $now));
    }

    public function testExpiredWhenDateEqualsNow(): void
    {
        $now = new \DateTimeImmutable('2026-03-31 12:00:00');
        $date = $now;

        $this->assertSame('expired', HumanReadableHelper::formatDateExpired($date, $now));
    }

    public function testInLessThanAnHour(): void
    {
        $now = new \DateTimeImmutable('2026-03-31 12:00:00');
        $date = $now->modify('+30 minutes');

        $this->assertSame('in less than an hour', HumanReadableHelper::formatDateExpired($date, $now));
    }

    public function testInHoursBetweenOneAnd24(): void
    {
        $now = new \DateTimeImmutable('2026-03-31 12:00:00');
        // 3 hours 15 minutes in the future -> should show hours part only
        $date = $now->modify('+3 hours 15 minutes');

        $this->assertSame('in 3 hours', HumanReadableHelper::formatDateExpired($date, $now));
    }

    public function testInDaysAndHours(): void
    {
        $now = new \DateTimeImmutable('2026-03-31 12:00:00');
        // 2 days and 5 hours in the future
        $date = $now->modify('+2 days')->modify('+5 hours');

        $this->assertSame('in 2 days 5 hours', HumanReadableHelper::formatDateExpired($date, $now));
    }

    public function testBoundaryExactlyOneHourAndTwentyFourHours(): void
    {
        $now = new \DateTimeImmutable('2026-03-31 12:00:00');

        $oneHour = $now->modify('+1 hour');
        $this->assertSame('in 1 hours', HumanReadableHelper::formatDateExpired($oneHour, $now));

        $twentyFour = $now->modify('+24 hours');
        $this->assertSame('in 1 days 0 hours', HumanReadableHelper::formatDateExpired($twentyFour, $now));
    }
}
