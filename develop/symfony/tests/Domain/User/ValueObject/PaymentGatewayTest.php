<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\ValueObject;

use App\Domain\User\ValueObject\PaymentGateway;
use PHPUnit\Framework\TestCase;

/**
 * Tests PaymentGateway enum — cases, static factories.
 */
final class PaymentGatewayTest extends TestCase
{
    /** Case'ы имеют корректные строковые значения. */
    public function testCasesHaveCorrectValues(): void
    {
        $this->assertSame('stripe', PaymentGateway::STRIPE->value);
        $this->assertSame('paypal', PaymentGateway::PAYPAL->value);
    }

    /** Статические фабрики возвращают правильный case. */
    public function testStaticFactories(): void
    {
        $this->assertSame(PaymentGateway::STRIPE, PaymentGateway::stripe());
        $this->assertSame(PaymentGateway::PAYPAL, PaymentGateway::paypal());
    }

    /** NAMES содержит все шлюзы. */
    public function testNamesConstant(): void
    {
        $this->assertArrayHasKey('stripe', PaymentGateway::NAMES);
        $this->assertArrayHasKey('paypal', PaymentGateway::NAMES);
        $this->assertSame('STRIPE', PaymentGateway::NAMES['stripe']);
        $this->assertSame('PAYPAL', PaymentGateway::NAMES['paypal']);
    }

    /** from() восстанавливает enum из строки. */
    public function testFromString(): void
    {
        $this->assertSame(PaymentGateway::STRIPE, PaymentGateway::from('stripe'));
        $this->assertSame(PaymentGateway::PAYPAL, PaymentGateway::from('paypal'));
    }

    /** Неизвестное значение бросает ValueError. */
    public function testFromUnknownStringThrows(): void
    {
        $this->expectException(\ValueError::class);
        PaymentGateway::from('yookassa');
    }
}
