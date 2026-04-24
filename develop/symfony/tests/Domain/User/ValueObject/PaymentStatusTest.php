<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\PaymentStatus;
use PHPUnit\Framework\TestCase;

/**
 * Tests PaymentStatus enum — cases, predicates, transitions.
 */
final class PaymentStatusTest extends TestCase
{
    /** Все case'ы существуют и возвращают правильные строковые значения. */
    public function testCasesHaveCorrectValues(): void
    {
        $this->assertSame('pending',   PaymentStatus::PENDING->value);
        $this->assertSame('completed', PaymentStatus::COMPLETED->value);
        $this->assertSame('failed',    PaymentStatus::FAILED->value);
        $this->assertSame('refunded',  PaymentStatus::REFUNDED->value);
        $this->assertSame('cancelled', PaymentStatus::CANCELLED->value);
    }

    /** Статические фабрики возвращают правильный case. */
    public function testStaticFactories(): void
    {
        $this->assertSame(PaymentStatus::PENDING,   PaymentStatus::pending());
        $this->assertSame(PaymentStatus::COMPLETED, PaymentStatus::completed());
        $this->assertSame(PaymentStatus::FAILED,    PaymentStatus::failed());
        $this->assertSame(PaymentStatus::REFUNDED,  PaymentStatus::refunded());
        $this->assertSame(PaymentStatus::CANCELLED, PaymentStatus::cancelled());
    }

    /** isPending() истинно только для PENDING. */
    public function testIsPredicates(): void
    {
        $this->assertTrue(PaymentStatus::PENDING->isPending());
        $this->assertFalse(PaymentStatus::COMPLETED->isPending());

        $this->assertTrue(PaymentStatus::COMPLETED->isCompleted());
        $this->assertFalse(PaymentStatus::PENDING->isCompleted());

        $this->assertTrue(PaymentStatus::FAILED->isFailed());
        $this->assertFalse(PaymentStatus::PENDING->isFailed());

        $this->assertTrue(PaymentStatus::REFUNDED->isRefunded());
        $this->assertFalse(PaymentStatus::PENDING->isRefunded());

        $this->assertTrue(PaymentStatus::CANCELLED->isCancelled());
        $this->assertFalse(PaymentStatus::PENDING->isCancelled());
    }

    /** isTerminal() истинно для FAILED, REFUNDED, CANCELLED; ложно для PENDING и COMPLETED. */
    public function testIsTerminal(): void
    {
        $this->assertFalse(PaymentStatus::PENDING->isTerminal());
        $this->assertFalse(PaymentStatus::COMPLETED->isTerminal());
        $this->assertTrue(PaymentStatus::FAILED->isTerminal());
        $this->assertTrue(PaymentStatus::REFUNDED->isTerminal());
        $this->assertTrue(PaymentStatus::CANCELLED->isTerminal());
    }

    /** Разрешения переходов: canBeCompleted/Failed/Cancelled только из PENDING; canBeRefunded только из COMPLETED. */
    public function testTransitionGuards(): void
    {
        $this->assertTrue(PaymentStatus::PENDING->canBeCompleted());
        $this->assertFalse(PaymentStatus::COMPLETED->canBeCompleted());
        $this->assertFalse(PaymentStatus::FAILED->canBeCompleted());

        $this->assertTrue(PaymentStatus::PENDING->canBeFailed());
        $this->assertFalse(PaymentStatus::COMPLETED->canBeFailed());

        $this->assertTrue(PaymentStatus::PENDING->canBeCancelled());
        $this->assertFalse(PaymentStatus::COMPLETED->canBeCancelled());

        $this->assertTrue(PaymentStatus::COMPLETED->canBeRefunded());
        $this->assertFalse(PaymentStatus::PENDING->canBeRefunded());
        $this->assertFalse(PaymentStatus::FAILED->canBeRefunded());
    }

    /** NAMES содержит все ожидаемые ключи. */
    public function testNamesConstant(): void
    {
        $this->assertArrayHasKey('pending',   PaymentStatus::NAMES);
        $this->assertArrayHasKey('completed', PaymentStatus::NAMES);
        $this->assertArrayHasKey('failed',    PaymentStatus::NAMES);
        $this->assertArrayHasKey('refunded',  PaymentStatus::NAMES);
        $this->assertArrayHasKey('cancelled', PaymentStatus::NAMES);
    }

    /** from() из строки возвращает правильный case. */
    public function testFromString(): void
    {
        $this->assertSame(PaymentStatus::COMPLETED, PaymentStatus::from('completed'));
    }
}
