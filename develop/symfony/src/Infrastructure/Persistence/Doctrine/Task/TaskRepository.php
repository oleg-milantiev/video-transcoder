<?php

namespace App\Infrastructure\Persistence\Doctrine\Task;

use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Repository\PresetRepositoryInterface;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\Preset\PresetEntity;
use App\Infrastructure\Persistence\Doctrine\Shared\Repository\PaginatedRepositoryTrait;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Persistence\Doctrine\Video\VideoEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskEntity>
 */
class TaskRepository extends ServiceEntityRepository implements TaskRepositoryInterface
{
    use PaginatedRepositoryTrait;

    public function __construct(
        ManagerRegistry $registry,
        private readonly VideoRepositoryInterface $videoRepository,
        private readonly PresetRepositoryInterface $presetRepository,
        private readonly UserRepositoryInterface $userRepository,
    )
    {
        parent::__construct($registry, TaskEntity::class);
    }

    /**
     * @throws ORMException
     */
    public function save(Task $task): void
    {
        $em = $this->getEntityManager();

        // TODO task update like in develop/symfony/src/Infrastructure/Persistence/Doctrine/Video/VideoRepository.php:37

        $taskEntity = TaskMapper::toDoctrine(
            $task,
            $em->getReference(VideoEntity::class, $task->videoId()),
            $em->getReference(PresetEntity::class, $task->presetId()),
            $em->getReference(UserEntity::class, $task->userId()),
        );
        $em->persist($taskEntity);
        $em->flush();
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
