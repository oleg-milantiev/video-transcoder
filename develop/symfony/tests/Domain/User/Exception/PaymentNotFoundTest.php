<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\Exception;

use App\Domain\User\Exception\PaymentNotFound;
use PHPUnit\Framework\TestCase;

/**
 * Tests PaymentNotFound exception — фабрика byId().
 */
final class PaymentNotFoundTest extends TestCase
{
    /** byId() возвращает исключение с ID в сообщении. */
    public function testByIdIncludesIdInMessage(): void
    {
        $id        = 'aaaa-bbbb-cccc';
        $exception = PaymentNotFound::byId($id);

        $this->assertInstanceOf(PaymentNotFound::class, $exception);
        $this->assertInstanceOf(\DomainException::class, $exception);
        $this->assertStringContainsString($id, $exception->getMessage());
    }
}
