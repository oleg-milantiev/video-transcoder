<?php
declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\ValueObject;

use App\Domain\Shared\Exception\InvalidUuidException;
use App\Domain\Shared\ValueObject\Uuid;
use PHPUnit\Framework\TestCase;

class UuidTest extends TestCase
{
    public function testGenerateReturnsValidUuidV4(): void
    {
        $uuid = Uuid::generate();

        $this->assertInstanceOf(Uuid::class, $uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid->toString()
        );
    }

    public function testFromStringCreatesValidUuid(): void
    {
        $uuidString = '123e4567-e89b-42d3-a456-426614174000';
        $uuid = Uuid::fromString($uuidString);

        $this->assertEquals($uuidString, $uuid->toString());
    }

    public function testFromStringWithInvalidFormatThrowsException(): void
    {
        $this->expectException(InvalidUuidException::class);
        $this->expectExceptionMessage('Invalid UUID v4 format: "not-a-uuid"');

        Uuid::fromString('not-a-uuid');
    }

    public function testFromStringWithWrongVersionThrowsException(): void
    {
        $this->expectException(InvalidUuidException::class);

        // UUID v1, not v4
        Uuid::fromString('123e4567-e89b-12d3-a456-426614174000');
    }

    public function testIsValidReturnsTrueForValidUuidV4(): void
    {
        $uuidString = Uuid::generate()->toString();

        $this->assertTrue(Uuid::isValid($uuidString));
    }

    public function testIsValidReturnsFalseForInvalidUuid(): void
    {
        $this->assertFalse(Uuid::isValid('not-a-uuid'));
        $this->assertFalse(Uuid::isValid('123e4567-e89b-12d3-a456-426614174000')); // v1
        $this->assertFalse(Uuid::isValid('123e4567-e89b-42d3-a456-42661417400')); // короткий
    }

    public function testEquals(): void
    {
        $uuid1 = Uuid::generate();
        $uuid2 = Uuid::fromString($uuid1->toString());
        $uuid3 = Uuid::generate();

        $this->assertTrue($uuid1->equals($uuid2));
        $this->assertFalse($uuid1->equals($uuid3));
    }

    public function testToString(): void
    {
        $uuidString = Uuid::generate()->toString();
        $uuid = Uuid::fromString($uuidString);

        $this->assertEquals($uuidString, (string) $uuid);
        $this->assertEquals($uuidString, $uuid->toString());
    }

    public function testGeneratedUuidsAreUnique(): void
    {
        $uuids = [];

        for ($i = 0; $i < 10000; $i++) {
            $uuids[] = Uuid::generate()->toString();
        }

        $this->assertCount(10000, array_unique($uuids));
    }
}
