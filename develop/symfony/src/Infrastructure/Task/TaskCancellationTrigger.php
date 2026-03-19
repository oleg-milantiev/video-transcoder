<?php

namespace App\Infrastructure\Task;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

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
    public function request(int $taskId, int $ttlSeconds = self::DEFAULT_TTL_SECONDS): void
    {
        $item = $this->cache->getItem($this->key($taskId));
        $item->set(true);
        $item->expiresAfter($ttlSeconds);
        $this->cache->save($item);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function isRequested(int $taskId): bool
    {
        return $this->cache->getItem($this->key($taskId))->isHit();
    }

    /**
     * @throws InvalidArgumentException
     */
    public function clear(int $taskId): void
    {
        $this->cache->deleteItem($this->key($taskId));
    }

    private function key(int $taskId): string
    {
        return sprintf('task_cancel_trigger_%d', $taskId);
    }
}

