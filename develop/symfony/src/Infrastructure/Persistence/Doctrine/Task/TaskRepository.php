<?php

namespace App\Infrastructure\Persistence\Doctrine\Task;

use App\Domain\Video\Entity\Task;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskEntity>
 */
class TaskRepository extends ServiceEntityRepository implements TaskRepositoryInterface
{
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
        return TaskMapper::toDomain($this->find($id));
    }
}
