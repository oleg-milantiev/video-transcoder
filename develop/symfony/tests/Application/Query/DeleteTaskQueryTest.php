<?php

declare(strict_types=1);

namespace App\Tests\Application\Query;

use App\Application\Exception\InvalidUuidException;
use App\Application\Query\DeleteTaskQuery;
use App\Domain\Shared\ValueObject\Uuid;
use PHPUnit\Framework\TestCase;

final class DeleteTaskQueryTest extends TestCase
{
    public function testConstructsWithValidUuids(): void
    {
        $taskId = Uuid::generate();
        $userId = Uuid::generate();

        $query = new DeleteTaskQuery($taskId->toRfc4122(), $userId->toRfc4122());

        $this->assertTrue($query->taskId->equals($taskId));
        $this->assertTrue($query->requestedByUserId->equals($userId));
    }

    public function testThrowsInvalidUuidExceptionForBadTaskId(): void
    {
        $this->expectException(InvalidUuidException::class);
        new DeleteTaskQuery('not-a-uuid', Uuid::generate()->toRfc4122());
    }

    public function testThrowsInvalidUuidExceptionForBadUserId(): void
    {
        $this->expectException(InvalidUuidException::class);
        new DeleteTaskQuery(Uuid::generate()->toRfc4122(), 'bad-user-id');
    }
}
