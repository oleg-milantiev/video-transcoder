<?php

declare(strict_types=1);

namespace App\Tests\Domain\Video\ValueObject;

use App\Domain\Video\ValueObject\RealtimeNotificationLevel;
use App\Domain\Video\ValueObject\RealtimeNotificationPosition;
use PHPUnit\Framework\TestCase;

final class RealtimeNotificationEnumsTest extends TestCase
{
    public function testLevelValues(): void
    {
        $this->assertSame('success', RealtimeNotificationLevel::SUCCESS->value);
        $this->assertSame('info', RealtimeNotificationLevel::INFO->value);
        $this->assertSame('warning', RealtimeNotificationLevel::WARNING->value);
        $this->assertSame('error', RealtimeNotificationLevel::ERROR->value);
    }

    public function testLevelFromString(): void
    {
        $this->assertSame(RealtimeNotificationLevel::SUCCESS, RealtimeNotificationLevel::from('success'));
        $this->assertSame(RealtimeNotificationLevel::INFO, RealtimeNotificationLevel::from('info'));
        $this->assertSame(RealtimeNotificationLevel::WARNING, RealtimeNotificationLevel::from('warning'));
        $this->assertSame(RealtimeNotificationLevel::ERROR, RealtimeNotificationLevel::from('error'));
    }

    public function testLevelTryFromReturnsNullForUnknown(): void
    {
        $this->assertNull(RealtimeNotificationLevel::tryFrom('unknown'));
    }

    public function testLevelCases(): void
    {
        $cases = RealtimeNotificationLevel::cases();

        $this->assertCount(4, $cases);
        $values = array_map(fn($c) => $c->value, $cases);
        $this->assertContains('success', $values);
        $this->assertContains('info', $values);
        $this->assertContains('warning', $values);
        $this->assertContains('error', $values);
    }

    public function testPositionValues(): void
    {
        $this->assertSame('top', RealtimeNotificationPosition::TOP->value);
        $this->assertSame('top-start', RealtimeNotificationPosition::TOP_START->value);
        $this->assertSame('top-end', RealtimeNotificationPosition::TOP_END->value);
        $this->assertSame('center', RealtimeNotificationPosition::CENTER->value);
        $this->assertSame('center-start', RealtimeNotificationPosition::CENTER_START->value);
        $this->assertSame('center-end', RealtimeNotificationPosition::CENTER_END->value);
        $this->assertSame('bottom', RealtimeNotificationPosition::BOTTOM->value);
        $this->assertSame('bottom-start', RealtimeNotificationPosition::BOTTOM_START->value);
        $this->assertSame('bottom-end', RealtimeNotificationPosition::BOTTOM_END->value);
    }

    public function testPositionFromString(): void
    {
        $this->assertSame(RealtimeNotificationPosition::TOP, RealtimeNotificationPosition::from('top'));
        $this->assertSame(RealtimeNotificationPosition::BOTTOM_END, RealtimeNotificationPosition::from('bottom-end'));
    }

    public function testPositionTryFromReturnsNullForUnknown(): void
    {
        $this->assertNull(RealtimeNotificationPosition::tryFrom('left'));
    }

    public function testPositionCases(): void
    {
        $cases = RealtimeNotificationPosition::cases();

        $this->assertCount(9, $cases);
    }
}
