<?php

declare(strict_types=1);

namespace App\Tests\Application\Query;

use App\Application\Exception\InvalidUuidException;
use App\Application\Query\StartTranscodeQuery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV4;

class StartTranscodeQueryTest extends TestCase
{
    public function testStoresUuidPresetAndUserIds(): void
    {
        $uuid = '123e4567-e89b-42d3-a456-426614174001';
        $presetId = '123e4567-e89b-42d3-a456-426614174005';
        $userId = UuidV4::fromString('123e4567-e89b-42d3-a456-426614174009');
        $query = new StartTranscodeQuery($uuid, presetId: $presetId, userId: $userId);

        $this->assertSame($uuid, $query->uuid->toRfc4122());
        $this->assertSame($presetId, $query->presetId->toRfc4122());
        $this->assertSame($userId->toRfc4122(), $query->userId->toRfc4122());
    }

    public function testInvalidUuidRaisesInvalidUuidException(): void
    {
        $this->expectException(InvalidUuidException::class);
        new StartTranscodeQuery('invalid', '123e4567-e89b-42d3-a456-426614174005', UuidV4::fromString('123e4567-e89b-42d3-a456-426614174009'));
    }
}

