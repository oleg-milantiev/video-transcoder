<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shared\Exception;

use App\Domain\Shared\Exception\InvalidUuidException;
use PHPUnit\Framework\TestCase;

/**
 * Tests InvalidUuidException — factory methods produce correct DomainException messages.
 */
final class InvalidUuidExceptionTest extends TestCase
{
    /** Проверяет, что сообщение содержит переданное значение в кавычках. */
    public function testInvalidFormatMessage(): void
    {
        $exception = InvalidUuidException::invalidFormat('not-a-uuid');

        $this->assertInstanceOf(\DomainException::class, $exception);
        $this->assertSame('Invalid UUID v4 format: "not-a-uuid"', $exception->getMessage());
    }

    /** Пустая строка тоже оборачивается в кавычки. */
    public function testEmptyStringMessage(): void
    {
        $exception = InvalidUuidException::invalidFormat('');

        $this->assertSame('Invalid UUID v4 format: ""', $exception->getMessage());
    }

    /** Исключение можно поймать как InvalidUuidException. */
    public function testIsThrowable(): void
    {
        $this->expectException(InvalidUuidException::class);
        $this->expectExceptionMessage('Invalid UUID v4 format: "bad"');

        throw InvalidUuidException::invalidFormat('bad');
    }
}
