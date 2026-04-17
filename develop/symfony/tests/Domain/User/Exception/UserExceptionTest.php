<?php

declare(strict_types=1);

namespace App\Tests\Domain\User\Exception;

use App\Domain\User\Exception\TariffNotFound;
use App\Domain\User\Exception\UserNotFound;
use PHPUnit\Framework\TestCase;

/**
 * Tests TariffNotFound и UserNotFound — сообщения фабричных методов и throwable-поведение.
 */
final class UserExceptionTest extends TestCase
{
    /** TariffNotFound содержит userId в сообщении и является DomainException. */
    public function testTariffNotFoundMessage(): void
    {
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $exception = TariffNotFound::forUser($userId);

        $this->assertInstanceOf(\DomainException::class, $exception);
        $this->assertSame("Tariff not found for user: {$userId}", $exception->getMessage());
    }

    /** TariffNotFound может быть пойман как TariffNotFound. */
    public function testTariffNotFoundIsThrowable(): void
    {
        $this->expectException(TariffNotFound::class);
        $this->expectExceptionMessage('Tariff not found for user: abc123');

        throw TariffNotFound::forUser('abc123');
    }

    /** UserNotFound содержит userId в сообщении и является DomainException. */
    public function testUserNotFoundMessage(): void
    {
        $userId = '123e4567-e89b-42d3-a456-426614174000';
        $exception = UserNotFound::byId($userId);

        $this->assertInstanceOf(\DomainException::class, $exception);
        $this->assertSame("User not found: {$userId}", $exception->getMessage());
    }

    /** UserNotFound может быть пойман как UserNotFound. */
    public function testUserNotFoundIsThrowable(): void
    {
        $this->expectException(UserNotFound::class);
        $this->expectExceptionMessage('User not found: xyz789');

        throw UserNotFound::byId('xyz789');
    }
}
