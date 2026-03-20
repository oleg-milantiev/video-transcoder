<?php

namespace App\Infrastructure\Persistence\Doctrine\Task;

use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\Video\DTO\ScheduledTaskDTO;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Repository\PresetRepositoryInterface;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\Preset\PresetEntity;
use App\Infrastructure\Persistence\Doctrine\Shared\Repository\PaginatedRepositoryTrait;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Persistence\Doctrine\Video\VideoEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\UuidV4;

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

        $videoRef = $em->getReference(VideoEntity::class, $task->videoId());
        $presetRef = $em->getReference(PresetEntity::class, $task->presetId());
        $userRef = $em->getReference(UserEntity::class, $task->userId());

        if ($task->id() === null) {
            $taskEntity = TaskMapper::toDoctrine($task, $videoRef, $presetRef, $userRef);
        } else {
            /** @var TaskEntity|null $taskEntity */
            $taskEntity = $this->find($task->id());
            if (!$taskEntity) {
                throw new \RuntimeException(sprintf('Task with id %d not found', $task->id()));
            }
            TaskMapper::hydrate($taskEntity, $task, $videoRef, $presetRef, $userRef);
        }

        $em->persist($taskEntity);
        $em->flush();

        $task->setId($taskEntity->id);
    }

    public function findById(int $id): ?Task
    {
        return self::mapToDomain($this->find($id));
    }

    /**
     * @throws ORMException
     */
    public function findByIdFresh(int $id): ?Task
    {
        $em = $this->getEntityManager();

        /** @var TaskEntity|null $entity */
        $entity = $this->find($id);
        if (!$entity) {
            return null;
        }

        $em->refresh($entity);

        return self::mapToDomain($entity);
    }

    public function findForTranscode(UuidV4 $videoId, int $presetId, int $userId): ?Task
    {
        $qb = $this->createQueryBuilder('task')
            ->andWhere('IDENTITY(task.video) = :videoId')
            ->andWhere('IDENTITY(task.preset) = :presetId')
            ->andWhere('IDENTITY(task.user) = :userId')
            ->setParameter('videoId', $videoId->toRfc4122())
            ->setParameter('presetId', $presetId)
            ->setParameter('userId', $userId)
            ->setMaxResults(1);

        /** @var TaskEntity|null $entity */
        $entity = $qb->getQuery()->getOneOrNullResult();

        return $entity ? self::mapToDomain($entity) : null;
    }

    /**
     * @throws Exception
     */
    public function getScheduled(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<'SQL'
            WITH user_metrics AS (
                -- Считаем текущую нагрузку пользователя и время последнего запуска
                SELECT
                    u.id AS user_id,
                    t.delay,
                    t.instance,
                    COUNT(CASE WHEN task.status = 2 THEN 1 END) AS active_count,
                    MAX(CASE WHEN task.status IN (2, 3, 4, 5) THEN COALESCE(task.started_at, task.created_at) END) AS last_start_time
                FROM "user" u
                         JOIN tariff t ON u.tariff_id = t.id
                         LEFT JOIN task task ON u.id = task.user_id
                GROUP BY u.id, t.delay, t.instance
            ),
                 pending_tasks AS (
                     -- Нумеруем задачи в очереди для каждого юзера
                     SELECT
                         tk.*,
                         ROW_NUMBER() OVER (PARTITION BY tk.user_id ORDER BY tk.created_at) as queue_pos,
                         um.active_count,
                         um.instance,
                         um.last_start_time,
                         um.delay
                     FROM task tk
                              JOIN user_metrics um ON tk.user_id = um.user_id
                     WHERE tk.status = 1 -- PENDING
                 )
            SELECT id AS task_id, user_id, video_id
            FROM pending_tasks
            WHERE
              -- 1. Не превышаем лимит одновременных задач
                (active_count + queue_pos) <= instance
              -- 2. Прошло достаточно времени с последнего запуска (если он был)
              AND (
                last_start_time IS NULL
                    -- TODO fields with timezone!
                    OR last_start_time <= NOW() AT TIME ZONE 'MSK' - (delay || ' seconds')::interval
                );
        SQL;

        $stmt = $conn->executeQuery($sql);

        return array_map(
            static fn (array $row): ScheduledTaskDTO => new ScheduledTaskDTO(
                (int) $row['task_id'],
                (int) $row['user_id'],
                UuidV4::fromString($row['video_id']),
            ),
            $stmt->fetchAllAssociative(),
        );
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
