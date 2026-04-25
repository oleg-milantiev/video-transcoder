<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\UserProfile;
use PHPUnit\Framework\TestCase;

/**
 * Tests UserProfile VO — create, empty, fromArray, toArray, withStats, withBilling,
 * round-trip serialization, инварианты, isSubscriptionActive.
 */
final class UserProfileTest extends TestCase
{
    // ── empty() ──────────────────────────────────────────────────────────────

    /** empty() возвращает VO со всеми нулями и null-датами. */
    public function testEmptyReturnsZeroFilledProfile(): void
    {
        $profile = UserProfile::empty();

        $this->assertSame(0,  $profile->videoCountActive);
        $this->assertSame(0,  $profile->videoCountTotal);
        $this->assertSame(0,  $profile->taskCountActive);
        $this->assertSame(0,  $profile->taskCountTotal);
        $this->assertSame([], $profile->taskCountByStatus);
        $this->assertSame(0,  $profile->storageUsedBytes);
        $this->assertSame(0,  $profile->storageDelete24Bytes);
        $this->assertSame('', $profile->willStartAt);
        $this->assertNull($profile->paidAt);
        $this->assertNull($profile->paidUntil);
        $this->assertSame([], $profile->paymentHistory);
    }

    // ── create() ─────────────────────────────────────────────────────────────

    /** create() сохраняет все переданные значения. */
    public function testCreateStoresAllValues(): void
    {
        $paidAt    = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $paidUntil = new \DateTimeImmutable('2027-01-01T00:00:00+00:00');
        $history   = [
            ['gateway' => 'stripe', 'method' => 'card', 'paidAt' => '2026-01-01T00:00:00+00:00',
             'amount' => 9900, 'currency' => 'USD', 'plan' => 'Pro'],
        ];

        $profile = UserProfile::create(
            videoCountActive:    3,
            videoCountTotal:     10,
            taskCountActive:     1,
            taskCountTotal:      50,
            taskCountByStatus:   [4 => 40, 5 => 5],
            storageUsedBytes:    104857600,
            storageDelete24Bytes: 10485760,
            willStartAt:         'in 3 minutes',
            paidAt:              $paidAt,
            paidUntil:           $paidUntil,
            paymentHistory:      $history,
        );

        $this->assertSame(3,          $profile->videoCountActive);
        $this->assertSame(10,         $profile->videoCountTotal);
        $this->assertSame(1,          $profile->taskCountActive);
        $this->assertSame(50,         $profile->taskCountTotal);
        $this->assertSame([4 => 40, 5 => 5], $profile->taskCountByStatus);
        $this->assertSame(104857600,  $profile->storageUsedBytes);
        $this->assertSame(10485760,   $profile->storageDelete24Bytes);
        $this->assertSame('in 3 minutes', $profile->willStartAt);
        $this->assertSame($paidAt,    $profile->paidAt);
        $this->assertSame($paidUntil, $profile->paidUntil);
        $this->assertSame($history,   $profile->paymentHistory);
    }

    /** create() без billing-аргументов устанавливает null/[] по умолчанию. */
    public function testCreateWithDefaultBilling(): void
    {
        $profile = UserProfile::create(1, 1, 0, 0, [], 0, 0, '');

        $this->assertNull($profile->paidAt);
        $this->assertNull($profile->paidUntil);
        $this->assertSame([], $profile->paymentHistory);
    }

    // ── Валидация ─────────────────────────────────────────────────────────────

    /** Отрицательный videoCountActive выбрасывает DomainException. */
    public function testNegativeVideoCountActiveThrows(): void
    {
        $this->expectException(\DomainException::class);
        UserProfile::create(-1, 0, 0, 0, [], 0, 0, '');
    }

    /** Отрицательный videoCountTotal выбрасывает DomainException. */
    public function testNegativeVideoCountTotalThrows(): void
    {
        $this->expectException(\DomainException::class);
        UserProfile::create(0, -1, 0, 0, [], 0, 0, '');
    }

    /** Отрицательный taskCountActive выбрасывает DomainException. */
    public function testNegativeTaskCountActiveThrows(): void
    {
        $this->expectException(\DomainException::class);
        UserProfile::create(0, 0, -1, 0, [], 0, 0, '');
    }

    /** Отрицательный storageUsedBytes выбрасывает DomainException. */
    public function testNegativeStorageUsedBytesThrows(): void
    {
        $this->expectException(\DomainException::class);
        UserProfile::create(0, 0, 0, 0, [], -1, 0, '');
    }

    /** paidUntil раньше paidAt выбрасывает DomainException. */
    public function testPaidUntilBeforePaidAtThrows(): void
    {
        $this->expectException(\DomainException::class);
        UserProfile::create(
            videoCountActive:    0,
            videoCountTotal:     0,
            taskCountActive:     0,
            taskCountTotal:      0,
            taskCountByStatus:   [],
            storageUsedBytes:    0,
            storageDelete24Bytes: 0,
            willStartAt:         '',
            paidAt:              new \DateTimeImmutable('2026-06-01'),
            paidUntil:           new \DateTimeImmutable('2026-01-01'),
        );
    }

    // ── toArray() / fromArray() ────────────────────────────────────────────────

    /** toArray() возвращает ожидаемую структуру с разделами statistics/storage/billing. */
    public function testToArrayHasExpectedStructure(): void
    {
        $paidAt    = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $paidUntil = new \DateTimeImmutable('2027-01-01T10:00:00+00:00');

        $profile = UserProfile::create(
            videoCountActive:    2,
            videoCountTotal:     8,
            taskCountActive:     0,
            taskCountTotal:      20,
            taskCountByStatus:   [4 => 18, 5 => 2],
            storageUsedBytes:    512,
            storageDelete24Bytes: 64,
            willStartAt:         'now',
            paidAt:              $paidAt,
            paidUntil:           $paidUntil,
            paymentHistory:      [['gateway' => 'stripe', 'amount' => 500]],
        );

        $array = $profile->toArray();

        $this->assertArrayHasKey('statistics', $array);
        $this->assertArrayHasKey('storage',    $array);
        $this->assertArrayHasKey('billing',    $array);

        $this->assertSame(2,   $array['statistics']['video']['active']);
        $this->assertSame(8,   $array['statistics']['video']['total']);
        $this->assertSame(0,   $array['statistics']['task']['active']);
        $this->assertSame(20,  $array['statistics']['task']['total']);
        $this->assertSame([4 => 18, 5 => 2], $array['statistics']['task']['byStatus']);
        $this->assertSame('now', $array['statistics']['task']['willStartAt']);

        $this->assertSame(512, $array['storage']['usedBytes']);
        $this->assertSame(64,  $array['storage']['delete24Bytes']);

        $this->assertSame($paidAt->format(\DateTimeInterface::ATOM),    $array['billing']['paidAt']);
        $this->assertSame($paidUntil->format(\DateTimeInterface::ATOM), $array['billing']['paidUntil']);
        $this->assertSame([['gateway' => 'stripe', 'amount' => 500]],    $array['billing']['history']);
    }

    /** toArray() сохраняет null-даты как null. */
    public function testToArrayWithNullDates(): void
    {
        $array = UserProfile::empty()->toArray();

        $this->assertNull($array['billing']['paidAt']);
        $this->assertNull($array['billing']['paidUntil']);
        $this->assertSame([], $array['billing']['history']);
    }

    /** Полный round-trip: create() → toArray() → fromArray() сохраняет все данные. */
    public function testRoundTrip(): void
    {
        $paidAt    = new \DateTimeImmutable('2026-03-15T12:00:00+00:00');
        $paidUntil = new \DateTimeImmutable('2027-03-15T12:00:00+00:00');
        $history   = [
            ['gateway' => 'yookassa', 'method' => 'bank_transfer',
             'paidAt' => '2026-03-15T12:00:00+00:00', 'amount' => 50000, 'currency' => 'RUB', 'plan' => 'Pro'],
        ];

        $original = UserProfile::create(
            videoCountActive:    5,
            videoCountTotal:     12,
            taskCountActive:     2,
            taskCountTotal:      30,
            taskCountByStatus:   [3 => 2, 4 => 25, 5 => 3],
            storageUsedBytes:    209715200,
            storageDelete24Bytes: 20971520,
            willStartAt:         'in 10 minutes',
            paidAt:              $paidAt,
            paidUntil:           $paidUntil,
            paymentHistory:      $history,
        );

        $restored = UserProfile::fromArray($original->toArray());

        $this->assertSame($original->videoCountActive,    $restored->videoCountActive);
        $this->assertSame($original->videoCountTotal,     $restored->videoCountTotal);
        $this->assertSame($original->taskCountActive,     $restored->taskCountActive);
        $this->assertSame($original->taskCountTotal,      $restored->taskCountTotal);
        $this->assertSame($original->storageUsedBytes,    $restored->storageUsedBytes);
        $this->assertSame($original->storageDelete24Bytes, $restored->storageDelete24Bytes);
        $this->assertSame($original->willStartAt,         $restored->willStartAt);
        $this->assertSame($original->paymentHistory,      $restored->paymentHistory);

        // Dates serialized and deserialized as ISO strings — compare by timestamp
        $this->assertSame(
            $original->paidAt->format(\DateTimeInterface::ATOM),
            $restored->paidAt->format(\DateTimeInterface::ATOM),
        );
        $this->assertSame(
            $original->paidUntil->format(\DateTimeInterface::ATOM),
            $restored->paidUntil->format(\DateTimeInterface::ATOM),
        );
    }

    /** fromArray() на пустом массиве возвращает нулевой профиль. */
    public function testFromArrayEmptyArrayReturnsZeroProfile(): void
    {
        $profile = UserProfile::fromArray([]);

        $this->assertSame(0,  $profile->videoCountActive);
        $this->assertSame(0,  $profile->storageUsedBytes);
        $this->assertNull($profile->paidAt);
        $this->assertNull($profile->paidUntil);
        $this->assertSame([], $profile->paymentHistory);
    }

    /** fromArray() игнорирует некорректные строки дат (возвращает null). */
    public function testFromArrayInvalidDateReturnsNull(): void
    {
        $profile = UserProfile::fromArray([
            'billing' => ['paidAt' => 'not-a-date', 'paidUntil' => null, 'history' => []],
        ]);

        $this->assertNull($profile->paidAt);
    }

    // ── withStats() / withBilling() ───────────────────────────────────────────

    /** withStats() обновляет статистику, billing остаётся без изменений. */
    public function testWithStatsUpdateIsImmutable(): void
    {
        $paidAt    = new \DateTimeImmutable('2026-01-01');
        $paidUntil = new \DateTimeImmutable('2027-01-01');

        $original = UserProfile::create(
            videoCountActive:    1,
            videoCountTotal:     1,
            taskCountActive:     0,
            taskCountTotal:      0,
            taskCountByStatus:   [],
            storageUsedBytes:    0,
            storageDelete24Bytes: 0,
            willStartAt:         '',
            paidAt:              $paidAt,
            paidUntil:           $paidUntil,
        );

        $updated = $original->withStats(10, 20, 5, 100, [4 => 90], 1048576, 0, 'in 1 minute');

        // Stats updated
        $this->assertSame(10, $updated->videoCountActive);
        $this->assertSame(20, $updated->videoCountTotal);
        $this->assertSame(1048576, $updated->storageUsedBytes);
        $this->assertSame('in 1 minute', $updated->willStartAt);

        // Billing preserved
        $this->assertSame($paidAt,    $updated->paidAt);
        $this->assertSame($paidUntil, $updated->paidUntil);

        // Original unchanged
        $this->assertSame(1, $original->videoCountActive);
        $this->assertNotSame($original, $updated);
    }

    /** withBilling() обновляет billing, статистика остаётся без изменений. */
    public function testWithBillingUpdateIsImmutable(): void
    {
        $original = UserProfile::create(3, 10, 1, 50, [], 1024, 0, 'now');

        $paidAt    = new \DateTimeImmutable('2026-06-01');
        $paidUntil = new \DateTimeImmutable('2027-06-01');
        $history   = [['gateway' => 'stripe', 'amount' => 9900]];

        $updated = $original->withBilling($paidAt, $paidUntil, $history);

        // Billing updated
        $this->assertSame($paidAt,    $updated->paidAt);
        $this->assertSame($paidUntil, $updated->paidUntil);
        $this->assertSame($history,   $updated->paymentHistory);

        // Stats preserved
        $this->assertSame(3,    $updated->videoCountActive);
        $this->assertSame(1024, $updated->storageUsedBytes);

        // Original unchanged
        $this->assertNull($original->paidAt);
        $this->assertNotSame($original, $updated);
    }

    // ── isSubscriptionActive() ───────────────────────────────────────────────

    /** isSubscriptionActive() ложно когда paidUntil = null. */
    public function testIsSubscriptionActiveWithNullPaidUntil(): void
    {
        $this->assertFalse(UserProfile::empty()->isSubscriptionActive());
    }

    /** isSubscriptionActive() истинно когда paidUntil в будущем. */
    public function testIsSubscriptionActiveWithFuturePaidUntil(): void
    {
        $profile = UserProfile::create(
            0, 0, 0, 0, [], 0, 0, '',
            paidAt:    new \DateTimeImmutable('-1 day'),
            paidUntil: new \DateTimeImmutable('+1 year'),
        );

        $this->assertTrue($profile->isSubscriptionActive());
    }

    /** isSubscriptionActive() ложно когда paidUntil в прошлом. */
    public function testIsSubscriptionActiveWithExpiredPaidUntil(): void
    {
        $profile = UserProfile::fromArray([
            'billing' => [
                'paidAt'    => (new \DateTimeImmutable('2022-01-01'))->format(\DateTimeInterface::ATOM),
                'paidUntil' => (new \DateTimeImmutable('2023-01-01'))->format(\DateTimeInterface::ATOM),
                'history'   => [],
            ],
        ]);

        $this->assertFalse($profile->isSubscriptionActive());
    }
}
