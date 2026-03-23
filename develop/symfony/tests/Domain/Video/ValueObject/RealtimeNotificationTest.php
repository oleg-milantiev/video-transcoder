<?php

declare(strict_types=1);

namespace Tests\Domain\Video\ValueObject;

use App\Domain\Video\Exception\InvalidRealtimeNotification;
use App\Domain\Video\ValueObject\RealtimeNotification;
use App\Domain\Video\ValueObject\RealtimeNotificationLevel;
use App\Domain\Video\ValueObject\RealtimeNotificationPosition;
use PHPUnit\Framework\TestCase;

final class RealtimeNotificationTest extends TestCase
{
    public function testCreatesNotificationWithValidData(): void
    {
        $notification = RealtimeNotification::create(
            level: RealtimeNotificationLevel::SUCCESS,
            title: 'Upload completed',
            html: 'Video uploaded. <a href="/video/1">Open</a>',
            timerMs: 7000,
            position: RealtimeNotificationPosition::TOP_END,
            imageUrl: '/uploads/video.jpg',
        );

        $this->assertSame('success', $notification->level()->value);
        $this->assertSame('Upload completed', $notification->title());
        $this->assertSame(7000, $notification->timerMs());
        $this->assertSame('top-end', $notification->position()->value);
        $this->assertSame('/uploads/video.jpg', $notification->imageUrl());
        $this->assertSame('Upload completed', $notification->imageAlt());
    }

    public function testThrowsWhenTitleIsEmpty(): void
    {
        $this->expectException(InvalidRealtimeNotification::class);

        RealtimeNotification::create(
            level: RealtimeNotificationLevel::INFO,
            title: '   ',
            html: 'Text',
        );
    }

    public function testThrowsWhenHtmlIsEmpty(): void
    {
        $this->expectException(InvalidRealtimeNotification::class);

        RealtimeNotification::create(
            level: RealtimeNotificationLevel::INFO,
            title: 'Valid',
            html: '  ',
        );
    }

    public function testThrowsOnInvalidTimer(): void
    {
        $this->expectException(InvalidRealtimeNotification::class);

        RealtimeNotification::create(
            level: RealtimeNotificationLevel::WARNING,
            title: 'Warning',
            html: 'Some html',
            timerMs: 60001,
        );
    }

    public function testThrowsOnInvalidImageUrl(): void
    {
        $this->expectException(InvalidRealtimeNotification::class);

        RealtimeNotification::create(
            level: RealtimeNotificationLevel::ERROR,
            title: 'Error',
            html: 'Failure',
            imageUrl: 'bad url',
        );
    }
}
