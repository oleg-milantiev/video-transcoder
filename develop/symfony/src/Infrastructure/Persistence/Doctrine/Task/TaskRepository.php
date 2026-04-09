<?php
declare(strict_types=1);

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

        $sql = "SELECT sum((COALESCE(t.meta->>'size', t.meta->>'sizeExpected'))::bigint)
            FROM task t
            WHERE t.user_id = :userId
                AND t.deleted = false";

        $size = $conn->executeQuery($sql, ['userId' => $userId->toRfc4122()])->fetchOne();

        return $size ? (int)$size : 0;
    }

    public function getScheduled(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<'SQL'
            WITH
                users_with_pending_tasks AS (
                    -- Только юзеры с Pending задачами
                    -- todo CREATE INDEX idx_task_user_active ON task (user_id) WHERE status = 1 AND deleted = false
                    SELECT DISTINCT user_id
                    FROM task
                    WHERE status = 1 AND deleted = false -- PENDING, not deleted
                ),
                user_metrics AS (
                    -- Считаем:
                    --   - колво Starting/Processing задач (active_count) для порога tariff.instance
                    --   - время последнего запуска (включая удалённые) для порога tariff.delay
                    -- todo CREATE INDEX idx_task_user_status_started ON task (user_id, status, started_at);
                    SELECT
                        t.user_id,
                        COUNT(CASE WHEN t.status IN (2, 3) THEN 1 END) AS active_count,
                        MAX(t.started_at) AS last_start_time
                    FROM task t
                    JOIN users_with_pending_tasks u ON t.user_id = u.user_id
                    GROUP BY t.user_id
                ),
                user_metrics_tariff AS (
                    -- Приклеить тарифные поля
                    -- todo CREATE INDEX idx_user_tariff_id ON "user" (tariff_id)
                    SELECT
                        m.*, -- user_id,active_count,last_start_time
                        tt.instance,
                        tt.delay
                    FROM user_metrics m
                    JOIN "user" u ON u.id = m.user_id
                    JOIN tariff tt ON tt.id = u.tariff_id
                ),
                ready_to_start AS (
                    -- Отбираем по одной задаче для каждого юзера, кто проходит по лимитам
                    -- todo CREATE INDEX idx_task_pending_queue ON task (user_id, created_at) WHERE status = 1 AND deleted = false;
                    SELECT DISTINCT ON (m.user_id)
                        t.id AS task_id
                    FROM task t
                    JOIN user_metrics_tariff m ON t.user_id = m.user_id
                    WHERE t.status = 1 AND t.deleted = false
                      -- Условие 1: Есть свободные слоты в тарифе
                      AND m.active_count < m.instance
                      -- Условие 2: Прошло достаточно времени (delay в секундах)
                      AND (m.last_start_time IS NULL OR m.last_start_time <= (NOW() - (m.delay || ' seconds')::interval))
                    ORDER BY m.user_id, t.created_at
                )
                UPDATE task
                SET status = 2, -- STARTING
                    updated_at = NOW()
                WHERE id IN (
                    SELECT task_id
                    FROM ready_to_start
                    FOR UPDATE SKIP LOCKED -- Пропускать задачи, которые другой экземпляр шедулера обновляет
                )
                RETURNING task.id AS task_id, task.user_id, task.video_id
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
