<?php

namespace App\Infrastructure\Persistence\Doctrine\Task;

use App\Domain\Video\Entity\Task;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\Shared\Repository\PaginatedRepositoryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskEntity>
 */
class TaskRepository extends ServiceEntityRepository implements TaskRepositoryInterface
{
    use PaginatedRepositoryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskEntity::class);
    }

    public function save(Task $task): void
    {
        $this->getEntityManager()->persist(TaskMapper::toDoctrine($task));
        $this->getEntityManager()->flush();
    }

    public function findById(int $id): ?Task
    {
        return self::mapToDomain($this->find($id));
    }

    protected static function mapToDomain(TaskEntity $entity): Task
    {
        return TaskMapper::toDomain($entity);
    }

    // You should NOT log into Persistence in prod. Just for debug now
    public function log(int $id, string $level, string $text): void
    {
        $em = $this->getEntityManager();
        /** @var TaskEntity|null $task */
        $task = $this->find($id);
        if (!$task) {
            throw new \RuntimeException("Task with id $id not found");
        }
        $log = $task->log ?? [];
        $log[] = [
            'level' => $level,
            'text' => $text,
            'timestamp' => new \DateTimeImmutable()->format(DATE_ATOM),
        ];
        $task->log = $log;
        $em->persist($task);
        $em->flush();
    }
}
