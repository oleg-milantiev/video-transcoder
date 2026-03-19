<?php

declare(strict_types=1);

namespace App\Tests\Application\Query;

use App\Application\Exception\InvalidUuidException;
use App\Application\Query\StartTranscodeQuery;
use PHPUnit\Framework\TestCase;

class StartTranscodeQueryTest extends TestCase
{
    public function testStoresUuidPresetAndUserIds(): void
    {
        $uuid = '123e4567-e89b-42d3-a456-426614174001';
        $query = new StartTranscodeQuery($uuid, presetId: 5, userId: 9);

        $this->assertSame($uuid, $query->uuid->toRfc4122());
        $this->assertSame(5, $query->presetId);
        $this->assertSame(9, $query->userId);
    }

    public function testInvalidUuidRaisesInvalidUuidException(): void
    {
        $this->expectException(InvalidUuidException::class);
        new StartTranscodeQuery('invalid', 1, 1);
    }
}

