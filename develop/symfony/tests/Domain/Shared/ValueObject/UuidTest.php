<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shared\ValueObject;

use App\Domain\Shared\Exception\InvalidUuidException;
use App\Domain\Shared\ValueObject\Uuid;
use PHPUnit\Framework\TestCase;

/**
 * Tests Uuid value object — generation, parsing, validation, equality and Stringable behaviour.
 */
final class UuidTest extends TestCase
{
    /** Uuid::generate() возвращает валидный UUID v4. */
    public function testGenerateReturnsValidUuidV4(): void
    {
        $uuid = Uuid::generate();

        $this->assertInstanceOf(Uuid::class, $uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid->toString()
        );
    }

    /** Uuid::fromString() успешно разбирает валидный UUID v4. */
    public function testFromStringCreatesValidUuid(): void
    {
        $uuidString = '123e4567-e89b-42d3-a456-426614174000';
        $uuid = Uuid::fromString($uuidString);

        $this->assertEquals($uuidString, $uuid->toString());
    }

    /** Невалидный формат вызывает InvalidUuidException. */
    public function testFromStringWithInvalidFormatThrowsException(): void
    {
        $this->expectException(InvalidUuidException::class);
        $this->expectExceptionMessage('Invalid UUID v4 format: "not-a-uuid"');

        Uuid::fromString('not-a-uuid');
    }

    /** UUID v1 отклоняется — принимается только v4. */
    public function testFromStringWithWrongVersionThrowsException(): void
    {
        $this->expectException(InvalidUuidException::class);

        Uuid::fromString('123e4567-e89b-12d3-a456-426614174000');
    }

    /** isValid() возвращает true для сгенерированного UUID v4. */
    public function testIsValidReturnsTrueForValidUuidV4(): void
    {
        $this->assertTrue(Uuid::isValid(Uuid::generate()->toString()));
    }

    /** isValid() возвращает false для невалидных строк и UUID не-v4 версии. */
    public function testIsValidReturnsFalseForInvalidUuid(): void
    {
        $this->assertFalse(Uuid::isValid('not-a-uuid'));
        $this->assertFalse(Uuid::isValid('123e4567-e89b-12d3-a456-426614174000')); // v1
        $this->assertFalse(Uuid::isValid('123e4567-e89b-42d3-a456-42661417400'));  // короткий
    }

    /** Два объекта с одинаковой строкой равны; с разными — нет. */
    public function testEquals(): void
    {
        $uuid1 = Uuid::generate();
        $uuid2 = Uuid::fromString($uuid1->toString());
        $uuid3 = Uuid::generate();

        $this->assertTrue($uuid1->equals($uuid2));
        $this->assertFalse($uuid1->equals($uuid3));
    }

    /** __toString() и toString() возвращают одну и ту же строку. */
    public function testToString(): void
    {
        $uuidString = Uuid::generate()->toString();
        $uuid = Uuid::fromString($uuidString);

        $this->assertEquals($uuidString, (string) $uuid);
        $this->assertEquals($uuidString, $uuid->toString());
    }

    /** fromStringNullable() возвращает объект для валидного UUID. */
    public function testFromStringNullableReturnsInstanceForValidUuid(): void
    {
        $this->assertInstanceOf(Uuid::class, Uuid::fromStringNullable(Uuid::generate()->toString()));
    }

    /** fromStringNullable() возвращает null для невалидной строки вместо выброса исключения. */
    public function testFromStringNullableReturnsNullForInvalidUuid(): void
    {
        $this->assertNull(Uuid::fromStringNullable('not-a-uuid'));
    }

    /** equals() принимает Uuid, строку и Stringable. */
    public function testEqualsAcceptsStringAndStringable(): void
    {
        $uuid = Uuid::generate();
        $uuidString = $uuid->toString();

        $this->assertTrue($uuid->equals($uuidString));

        $stringable = new class($uuidString) implements \Stringable {
            public function __construct(private readonly string $s) {}
            public function __toString(): string { return $this->s; }
        };

        $this->assertTrue($uuid->equals($stringable));
        $this->assertFalse($uuid->equals('some-other-uuid'));
    }

    /** toRfc4122() возвращает ту же строку, что и toString(). */
    public function testToRfc4122ReturnsSameString(): void
    {
        $uuidString = Uuid::generate()->toString();

        $this->assertEquals($uuidString, Uuid::fromString($uuidString)->toRfc4122());
    }

    /** isValid() регистронезависим и отклоняет пустую строку. */
    public function testIsValidIsCaseInsensitiveAndRejectsEmpty(): void
    {
        $upper = strtoupper(Uuid::generate()->toString());

        $this->assertTrue(Uuid::isValid($upper));
        $this->assertFalse(Uuid::isValid(''));
    }

    /** 10 000 последовательных Uuid::generate() не дают коллизий. */
    public function testGeneratedUuidsAreUnique(): void
    {
        $uuids = [];

        for ($i = 0; $i < 10000; $i++) {
            $uuids[] = Uuid::generate()->toString();
        }

        $this->assertCount(10000, array_unique($uuids));
    }
}
