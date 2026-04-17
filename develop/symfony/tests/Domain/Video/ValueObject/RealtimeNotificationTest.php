<?php

declare(strict_types=1);

namespace App\Tests\Domain\Video\ValueObject;

use App\Domain\Video\Exception\InvalidRealtimeNotification;
use App\Domain\Video\ValueObject\RealtimeNotification;
use App\Domain\Video\ValueObject\RealtimeNotificationLevel;
use App\Domain\Video\ValueObject\RealtimeNotificationPosition;
use PHPUnit\Framework\TestCase;

/**
 * Tests RealtimeNotification — создание уведомления с валидацией title, html, timerMs, imageUrl
 * и нормализация imageAlt (по умолчанию = title).
 */
final class RealtimeNotificationTest extends TestCase
{
    /** Все поля сохраняются; imageAlt по умолчанию равен title. */
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

    /** Пустой (пробельный) title бросает InvalidRealtimeNotification. */
    public function testThrowsWhenTitleIsEmpty(): void
    {
        $this->expectException(InvalidRealtimeNotification::class);

        RealtimeNotification::create(
            level: RealtimeNotificationLevel::INFO,
            title: '   ',
            html: 'Text',
        );
    }

    /** Пустой (пробельный) html бросает InvalidRealtimeNotification. */
    public function testThrowsWhenHtmlIsEmpty(): void
    {
        $this->expectException(InvalidRealtimeNotification::class);

        RealtimeNotification::create(
            level: RealtimeNotificationLevel::INFO,
            title: 'Valid',
            html: '  ',
        );
    }

    /** timerMs > MAX_TIMER_MS бросает InvalidRealtimeNotification. */
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

    /** imageUrl, не являющийся абсолютным URL или путём, бросает InvalidRealtimeNotification. */
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

    /** title длиннее 140 символов бросает InvalidRealtimeNotification. */
    public function testThrowsWhenTitleTooLong(): void
    {
        $this->expectException(InvalidRealtimeNotification::class);

        RealtimeNotification::create(
            level: RealtimeNotificationLevel::INFO,
            title: str_repeat('a', 141),
            html: 'Some text',
        );
    }

    /** html() возвращает переданный HTML. */
    public function testHtmlGetterReturnsHtml(): void
    {
        $notification = RealtimeNotification::create(
            level: RealtimeNotificationLevel::INFO,
            title: 'Title',
            html: '<b>Content</b>',
        );

        $this->assertSame('<b>Content</b>', $notification->html());
    }

    /** null imageUrl даёт null imageUrl и null imageAlt. */
    public function testNullImageUrlProducesNullImageAlt(): void
    {
        $notification = RealtimeNotification::create(
            level: RealtimeNotificationLevel::INFO,
            title: 'Title',
            html: 'Content',
            imageUrl: null,
        );

        $this->assertNull($notification->imageUrl());
        $this->assertNull($notification->imageAlt());
    }

    /** Явно переданный imageAlt используется вместо title. */
    public function testExplicitImageAltIsUsedWhenProvided(): void
    {
        $notification = RealtimeNotification::create(
            level: RealtimeNotificationLevel::SUCCESS,
            title: 'Title',
            html: 'Content',
            imageUrl: '/img/thumb.jpg',
            imageAlt: 'Custom alt text',
        );

        $this->assertSame('Custom alt text', $notification->imageAlt());
    }

    /** Пустая пробельная строка imageUrl нормализуется в null. */
    public function testEmptyStringImageUrlTreatedAsNull(): void
    {
        $notification = RealtimeNotification::create(
            level: RealtimeNotificationLevel::INFO,
            title: 'Title',
            html: 'Content',
            imageUrl: '   ',
        );

        $this->assertNull($notification->imageUrl());
        $this->assertNull($notification->imageAlt());
    }
}
