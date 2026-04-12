<?php

declare(strict_types=1);

namespace App\Tests\Application\Query;

use App\Application\Exception\InvalidUuidException;
use App\Application\Query\TaskCancelQuery;
use PHPUnit\Framework\TestCase;

final class TaskCancelQueryTest extends TestCase
{
    public function testCreatesQueryWithValidData(): void
    {
        $taskId = '11111111-1111-4111-8111-111111111111';
        $userId = '22222222-2222-4222-8222-222222222222';

        $query = new TaskCancelQuery($taskId, $userId);

        $this->assertSame($taskId, $query->taskId->toRfc4122());
        $this->assertSame($userId, $query->requestedByUserId->toRfc4122());
    }

    public function testThrowsWhenTaskIdIsInvalid(): void
    {
        $this->expectException(InvalidUuidException::class);
        new TaskCancelQuery('not-a-uuid', '22222222-2222-4222-8222-222222222222');
    }

    public function testThrowsWhenUserIdIsInvalid(): void
    {
        $this->expectException(InvalidUuidException::class);
        new TaskCancelQuery('11111111-1111-4111-8111-111111111111', 'not-a-uuid');
    }
}
