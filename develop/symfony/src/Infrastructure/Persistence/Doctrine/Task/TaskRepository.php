<?php

namespace App\Infrastructure\Persistence\Doctrine\Task;

use App\Application\DTO\ScheduledTaskDTO;
use App\Application\Query\Repository\ScheduledTaskReadRepositoryInterface;
use App\Domain\Shared\ValueObject\Uuid;
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
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

/**
 * @extends ServiceEntityRepository<TaskEntity>
 */
class TaskRepository extends ServiceEntityRepository implements TaskRepositoryInterface, ScheduledTaskReadRepositoryInterface
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

        $videoRef = $em->getReference(VideoEntity::class, SymfonyUuid::fromString($task->videoId()->toRfc4122()));
        $presetRef = $em->getReference(PresetEntity::class, SymfonyUuid::fromString($task->presetId()->toRfc4122()));
        $userRef = $em->getReference(UserEntity::class, SymfonyUuid::fromString($task->userId()->toRfc4122()));

        if ($task->id() === null) {
            $taskEntity = TaskMapper::toDoctrine($task, $videoRef, $presetRef, $userRef);
        } else {
            /** @var TaskEntity|null $taskEntity */
            $taskEntity = $this->find(SymfonyUuid::fromString($task->id()->toRfc4122()));
            if (!$taskEntity) {
                throw new \RuntimeException(sprintf('Task with id %s not found', $task->id()->toRfc4122()));
            }
            TaskMapper::hydrate($taskEntity, $task, $videoRef, $presetRef, $userRef);
        }

        $em->persist($taskEntity);
        $em->flush();

        if ($task->id() === null) {
            $task->assignId(Uuid::fromString($taskEntity->id->toRfc4122()));
        }
    }

    public function findById(Uuid $id): ?Task
    {
        $entity = $this->find(SymfonyUuid::fromString($id->toRfc4122()));

        return $entity ? self::mapToDomain($entity) : null;
    }

    /**
     * @throws ORMException
     */
    public function findByIdFresh(Uuid $id): ?Task
    {
        $em = $this->getEntityManager();

        /** @var TaskEntity|null $entity */
        $entity = $this->find(SymfonyUuid::fromString($id->toRfc4122()));
        if (!$entity) {
            return null;
        }

        $em->refresh($entity);

        return self::mapToDomain($entity);
    }

    public function findForTranscode(Uuid $videoId, Uuid $presetId, Uuid $userId): ?Task
    {
        $qb = $this->createQueryBuilder('task')
            ->andWhere('IDENTITY(task.video) = :videoId')
            ->andWhere('IDENTITY(task.preset) = :presetId')
            ->andWhere('IDENTITY(task.user) = :userId')
            ->setParameter('videoId', $videoId->toRfc4122())
            ->setParameter('presetId', $presetId->toRfc4122())
            ->setParameter('userId', $userId->toRfc4122())
            ->setMaxResults(1);

        /** @var TaskEntity|null $entity */
        $entity = $qb->getQuery()->getOneOrNullResult();

        return $entity ? self::mapToDomain($entity) : null;
    }

    public function findByVideoId(Uuid $videoId): array
    {
        $entities = $this->findBy([
            'video' => SymfonyUuid::fromString($videoId->toRfc4122()),
        ]);

        return array_map(static fn (TaskEntity $entity): Task => self::mapToDomain($entity), $entities);
    }

    public function findDeletedTaskForCleanup(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<'SQL'
            SELECT id
            FROM task
            WHERE deleted = true
              AND COALESCE(meta::jsonb ->> 'output', '') <> ''
            ORDER BY updated_at NULLS FIRST, created_at
        SQL;

        $rows = $conn->executeQuery($sql)->fetchAllAssociative();

        return array_map(function (array $row): ?Task {
            return $this->findById(Uuid::fromString($row['id']));
        }, $rows);
    }

    public function getStorageSize(Uuid $userId): int
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "SELECT sum((t.meta->>'size')::bigint)
            FROM task t
            WHERE t.user_id = :userId
                AND t.deleted = false";

        $size = $conn->executeQuery($sql, ['userId' => $userId->toRfc4122()])->fetchOne();

        return $size ? (int)$size : 0;
    }

    /**
     * @throws Exception
     */
    public function getScheduled(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<'SQL'
            WITH user_metrics AS (
                -- Считаем текущую нагрузку пользователя и время последнего запуска (включая удалённые)
                SELECT
                    u.id AS user_id,
                    t.delay,
                    t.instance,
                    COUNT(CASE WHEN task.status = 2 THEN 1 END) AS active_count,
                    MAX(CASE WHEN task.status IN (2, 3, 4, 5, 6) THEN COALESCE(task.started_at, task.created_at) END) AS last_start_time
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
                     WHERE tk.status = 1 AND tk.deleted = false -- PENDING, not deleted
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
                    OR last_start_time <= NOW() - (delay || ' seconds')::interval
                );
        SQL;

        $stmt = $conn->executeQuery($sql);

        return array_map(
            static fn (array $row): ScheduledTaskDTO => new ScheduledTaskDTO(
                Uuid::fromString($row['task_id']),
                Uuid::fromString($row['user_id']),
                Uuid::fromString($row['video_id']),
            ),
            $stmt->fetchAllAssociative(),
        );
    }

    protected static function mapToDomain(TaskEntity $entity): Task
    {
        return TaskMapper::toDomain($entity);
    }
}
