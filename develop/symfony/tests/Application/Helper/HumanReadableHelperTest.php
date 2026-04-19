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

    // Tests for formatDuration
    public function testFormatDurationZeroSeconds(): void
    {
        $this->assertSame('00:00:00', HumanReadableHelper::formatDuration(0));
    }

    public function testFormatDurationOnlySeconds(): void
    {
        $this->assertSame('00:00:45', HumanReadableHelper::formatDuration(45));
    }

    public function testFormatDurationMinutesAndSeconds(): void
    {
        $this->assertSame('00:05:30', HumanReadableHelper::formatDuration(330)); // 5 min 30 sec
    }

    public function testFormatDurationHoursMinutesAndSeconds(): void
    {
        $this->assertSame('02:15:45', HumanReadableHelper::formatDuration(8145)); // 2h 15m 45s
    }

    public function testFormatDurationLargeValues(): void
    {
        // 24 hours = 86400 seconds
        $this->assertSame('24:00:00', HumanReadableHelper::formatDuration(86400));
    }

    // Tests for formatFileSize
    public function testFormatFileSizeSmallFile(): void
    {
        // 10 MB
        $this->assertSame('10.00 MB', HumanReadableHelper::formatFileSize(10 * 1024 * 1024));
    }

    public function testFormatFileSizeLargeFile(): void
    {
        // 500 MB
        $this->assertSame('500.00 MB', HumanReadableHelper::formatFileSize(500 * 1024 * 1024));
    }

    public function testFormatFileSizeDecimalValues(): void
    {
        // 1.5 MB
        $this->assertSame('1.50 MB', HumanReadableHelper::formatFileSize((int)(1.5 * 1024 * 1024)));
    }

    public function testFormatFileSizeZeroBytes(): void
    {
        $this->assertSame('0.00 MB', HumanReadableHelper::formatFileSize(0));
    }

    public function testFormatFileSizeVerySmallFile(): void
    {
        // 1024 bytes = 1 KB = 0.00097... MB
        $this->assertSame('0.00 MB', HumanReadableHelper::formatFileSize(1024));
    }

    // Tests for formatBitrate
    public function testFormatBitrateLowSpeed(): void
    {
        // 5 Mbps
        $this->assertSame('5.00 Mbps', HumanReadableHelper::formatBitrate(5 * 1024 * 1024));
    }

    public function testFormatBitrateHighSpeed(): void
    {
        // 10 Mbps
        $this->assertSame('10.00 Mbps', HumanReadableHelper::formatBitrate(10 * 1024 * 1024));
    }

    public function testFormatBitrateDecimalValues(): void
    {
        // 2.5 Mbps
        $this->assertSame('2.50 Mbps', HumanReadableHelper::formatBitrate((int)(2.5 * 1024 * 1024)));
    }

    public function testFormatBitrateZero(): void
    {
        $this->assertSame('0.00 Mbps', HumanReadableHelper::formatBitrate(0));
    }

    public function testFormatBitrateVeryLow(): void
    {
        // 1024 bits/sec = 0.00097... Mbps
        $this->assertSame('0.00 Mbps', HumanReadableHelper::formatBitrate(1024));
    }
}
