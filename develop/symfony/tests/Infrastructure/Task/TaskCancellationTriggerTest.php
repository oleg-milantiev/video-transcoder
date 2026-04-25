<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Task;

use App\Domain\Shared\ValueObject\Uuid;
use App\Infrastructure\Task\TaskCancellationTrigger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class TaskCancellationTriggerTest extends TestCase
{
    private TaskCancellationTrigger $trigger;
    private Uuid $taskId;

    protected function setUp(): void
    {
        $this->trigger = new TaskCancellationTrigger(new ArrayAdapter());
        $this->taskId = Uuid::fromString('99999999-9999-4999-8999-999999999999');
    }

    public function testIsNotRequestedByDefault(): void
    {
        self::assertFalse($this->trigger->isRequested($this->taskId));
    }

    public function testRequestMarksAsRequested(): void
    {
        $this->trigger->request($this->taskId);

        self::assertTrue($this->trigger->isRequested($this->taskId));
    }

    public function testClearRemovesRequest(): void
    {
        $this->trigger->request($this->taskId);
        $this->trigger->clear($this->taskId);

        self::assertFalse($this->trigger->isRequested($this->taskId));
    }

    public function testDifferentTaskIdsAreIndependent(): void
    {
        $otherId = Uuid::fromString('88888888-8888-4888-8888-888888888888');

        $this->trigger->request($this->taskId);

        self::assertTrue($this->trigger->isRequested($this->taskId));
        self::assertFalse($this->trigger->isRequested($otherId));
    }

    public function testClearNonExistentKeyDoesNotThrow(): void
    {
        $this->trigger->clear($this->taskId); // nothing set yet

        self::assertFalse($this->trigger->isRequested($this->taskId));
    }

    public function testRequestWithShortTtlExpires(): void
    {
        // ArrayAdapter supports TTL — with ttl=1 and sleep(2) it should expire
        $this->trigger->request($this->taskId, ttlSeconds: 1);
        self::assertTrue($this->trigger->isRequested($this->taskId));

        sleep(2);
        // ArrayAdapter respects TTL on getItem() after expiry
        self::assertFalse($this->trigger->isRequested($this->taskId));
    }
}
