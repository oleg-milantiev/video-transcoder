<?php

namespace App\Infrastructure\Task;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\UuidV4;

// TODO подумать, тут ли ему лежать
final readonly class TaskCancellationTrigger
{
    private const int DEFAULT_TTL_SECONDS = 86400;

    public function __construct(
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
    ) {}

    /**
     * @throws InvalidArgumentException
     */
    public function request(UuidV4 $taskId, int $ttlSeconds = self::DEFAULT_TTL_SECONDS): void
    {
        $item = $this->cache->getItem($this->key($taskId));
        $item->set(true);
        $item->expiresAfter($ttlSeconds);
        $this->cache->save($item);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function isRequested(UuidV4 $taskId): bool
    {
        return $this->cache->getItem($this->key($taskId))->isHit();
    }

    /**
     * @throws InvalidArgumentException
     */
    public function clear(UuidV4 $taskId): void
    {
        $this->cache->deleteItem($this->key($taskId));
    }

    private function key(UuidV4 $taskId): string
    {
        return sprintf('task_cancel_trigger_%s', $taskId->toRfc4122());
    }
}

