<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shared\Exception;

use App\Domain\Shared\Exception\InvalidUuidException;
use PHPUnit\Framework\TestCase;

final class InvalidUuidExceptionTest extends TestCase
{
    public function testInvalidFormatMessage(): void
    {
        $exception = InvalidUuidException::invalidFormat('not-a-uuid');

        $this->assertInstanceOf(\DomainException::class, $exception);
        $this->assertSame('Invalid UUID v4 format: "not-a-uuid"', $exception->getMessage());
    }

    public function testEmptyStringMessage(): void
    {
        $exception = InvalidUuidException::invalidFormat('');

        $this->assertSame('Invalid UUID v4 format: ""', $exception->getMessage());
    }

    public function testIsThrowable(): void
    {
        $this->expectException(InvalidUuidException::class);
        $this->expectExceptionMessage('Invalid UUID v4 format: "bad"');

        throw InvalidUuidException::invalidFormat('bad');
    }
}
