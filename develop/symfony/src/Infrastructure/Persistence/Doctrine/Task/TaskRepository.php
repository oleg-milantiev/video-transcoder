<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Task;

use App\Application\DTO\ScheduledTaskDTO;
use App\Application\DTO\TaskItemDTO;
use App\Application\Query\Repository\ScheduledTaskReadRepositoryInterface;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Repository\PresetRepositoryInterface;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Infrastructure\Persistence\Doctrine\Preset\PresetEntity;
use App\Infrastructure\Persistence\Doctrine\Shared\Repository\PaginatedRepositoryTrait;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Persistence\Doctrine\Video\VideoEntity;
use DateMalformedStringException;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;
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
    ) {
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
                throw new RuntimeException(sprintf('Task with id %s not found', $task->id()->toRfc4122()));
            }
            TaskMapper::hydrate($taskEntity, $task, $videoRef, $presetRef, $userRef);
        }

        $em->persist($taskEntity);
        $em->flush();

        if ($task->id() === null) {
            $task->assignId(Uuid::fromString($taskEntity->id->toRfc4122()));
        }
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

    protected static function mapToDomain(TaskEntity $entity): Task
    {
        return TaskMapper::toDomain($entity);
    }

    /**
     * @throws Exception
     */
    public function findForTranscode(Uuid $videoId, Uuid $presetId, Uuid $userId, int $height): ?Task
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<'SQL'
            SELECT id
            FROM task
            WHERE video_id = :videoId
              AND preset_id = :presetId
              AND user_id = :userId
              AND (meta->>'height')::int = :height
            LIMIT 1
        SQL;

        $row = $conn->executeQuery($sql, [
            'videoId' => $videoId->toRfc4122(),
            'presetId' => $presetId->toRfc4122(),
            'userId' => $userId->toRfc4122(),
            'height' => $height,
        ])->fetchAssociative();

        if (!$row) {
            return null;
        }

        $entity = $this->find(SymfonyUuid::fromString($row['id']));

        return $entity ? self::mapToDomain($entity) : null;
    }

    public function findByVideoId(Uuid $videoId): array
    {
        $entities = $this->findBy([
            'video' => SymfonyUuid::fromString($videoId->toRfc4122()),
        ]);

        return array_map(static fn(TaskEntity $entity): Task => self::mapToDomain($entity), $entities);
    }

    /**
     * @throws Exception
     */
    public function getDetailsByVideoId(Uuid $videoId): array
    {
        $conn = $this->getEntityManager()->getConnection();

        // todo DRY с шедулером и getFirstPendingTaskWillStartAt в view
        $sql = <<<SQL
            WITH
            user_metrics AS (
                 -- from scheduler for one user.id
                 SELECT
                     t.user_id,
                     COUNT(CASE WHEN t.status IN (2, 3) THEN 1 END) AS active_count, -- щас выполняется N
                     MAX(t.started_at) AS last_start_time                            -- последний запуск в хх:хх:хх
                 FROM task t
                 WHERE t.user_id = (SELECT v.user_id FROM video v WHERE v.id = :video_id)
                 GROUP BY t.user_id
             ),
             user_metrics_tariff AS (
                 SELECT
                     m.*, -- user_id,active_count,last_start_time
                     tt.instance,
                     tt.delay
                 FROM user_metrics m
                          JOIN "user" u ON u.id = m.user_id
                          JOIN tariff tt ON tt.id = u.tariff_id
            ),
            pending_starting_tasks AS (
                SELECT
                    t.id,
                    active_count >= instance AS waiting_tariff_instance,
                    last_start_time + (m.delay || ' seconds')::interval > now() AS waiting_tariff_delay,
                    last_start_time + (m.delay || ' seconds')::interval AS will_start_at
                FROM task t
                JOIN user_metrics_tariff m ON t.user_id = m.user_id
                WHERE t.video_id = :video_id -- todo CREATE INDEX idx_task_video_id ON task (video_id)
                    AND t.status IN (1, 2) AND t.deleted = false
            )
            SELECT
                t.id,
                t.video_id,
                v.title AS video_title,
                p.id AS preset_id,
                p.video_codec AS preset_video_codec,
                p.audio_codec AS preset_audio_codec,
                p.format AS preset_format,
                p.title AS preset_title,
                (t.meta->>'height')::int AS meta_height,
                t.status,
                t.progress,
                t.created_at,
                t.deleted,
                pst.waiting_tariff_instance,
                pst.waiting_tariff_delay,
                to_char(pst.will_start_at AT TIME ZONE 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS"+00:00"') AS will_start_at,
                (t.meta->>'size')::bigint AS size
            FROM task t
                     JOIN preset p ON p.id = t.preset_id
                     JOIN video v ON v.id = t.video_id
                     LEFT JOIN pending_starting_tasks pst on t.id = pst.id
            WHERE t.video_id = :video_id
            ORDER BY p.video_codec, p.audio_codec, p.format, (t.meta->>'height')::int DESC
        SQL;

        $stmt = $conn->executeQuery($sql, ['video_id' => $videoId->toRfc4122()]);

        return array_map(
            static fn(array $row): TaskItemDTO => new TaskItemDTO( // todo via TaskItemDTO::some-static
                id: $row['id'],
                videoId: $row['video_id'],
                videoTitle: $row['video_title'],
                presetId: $row['preset_id'],
                presetVideoCodec: $row['preset_video_codec'],
                presetAudioCodec: $row['preset_audio_codec'],
                presetFormat: $row['preset_format'],
                presetTitle: $row['preset_title'],
                height: (int)$row['meta_height'],
                status: TaskStatus::tryFrom((int)$row['status'])?->name,
                progress: $row['progress'],
                createdAt: $row['created_at'],
                deleted: (bool)$row['deleted'],
                waitingTariffInstance: (bool)$row['waiting_tariff_instance'],
                waitingTariffDelay: (bool)$row['waiting_tariff_delay'],
                willStartAt: $row['will_start_at'],
                size: $row['size'] !== null ? (int)$row['size'] : null,
            ),
            $stmt->fetchAllAssociative(),
        );
    }

    /**
     * @throws Exception
     */
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

    public function findById(Uuid $id): ?Task
    {
        $entity = $this->find(SymfonyUuid::fromString($id->toRfc4122()));

        return $entity ? self::mapToDomain($entity) : null;
    }

    /**
     * @throws Exception
     */
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
            static fn(array $row): ScheduledTaskDTO => new ScheduledTaskDTO(
                Uuid::fromString($row['task_id']),
                Uuid::fromString($row['user_id']),
                Uuid::fromString($row['video_id']),
            ),
            $stmt->fetchAllAssociative(),
        );
    }

    /**
     * @throws Exception
     */
    public function getActiveCountByStatus(Uuid $userId): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = <<<'SQL'
            SELECT status, COUNT(id) AS count
            FROM task
            WHERE user_id = :user_id AND deleted = false
            GROUP BY status
        SQL;

        $ret = [];
        foreach ($conn->executeQuery($sql, ['user_id' => $userId->toRfc4122()])->fetchAllAssociative() as $row) {
            $ret[$row['status']] = (int)$row['count'];
        }

        return $ret;
    }

    /**
     * @throws Exception
     */
    public function getActiveCount(Uuid $userId): int
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<< SQL
            SELECT count(*) AS c
            FROM task t
            WHERE t.user_id = :user_id
              AND t.deleted = false
        SQL;

        return (int)$conn->executeQuery($sql, ['user_id' => $userId->toRfc4122()])->fetchOne();
    }

    /**
     * @throws Exception
     */
    public function getTotalCount(Uuid $userId): int
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<< SQL
            SELECT count(*) AS c
            FROM task t
            WHERE t.user_id = :user_id
        SQL;

        return (int)$conn->executeQuery($sql, ['user_id' => $userId->toRfc4122()])->fetchOne();
    }

    /**
     * @throws Exception
     * @throws DateMalformedStringException
     */
    public function getFirstPendingTaskWillStartAt(Uuid $userId): ?DateTimeImmutable
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<< SQL
            WITH
            user_metrics AS (
                -- from scheduler for one user.id
                SELECT
                    t.user_id,
                    COUNT(CASE WHEN t.status IN (2, 3) THEN 1 END) AS active_count, -- щас выполняется N
                    MAX(t.started_at) AS last_start_time                            -- последний запуск в хх:хх:хх
                FROM task t
                WHERE t.user_id = :user_id
                GROUP BY t.user_id
            ),
            user_metrics_tariff AS (
                SELECT
                    m.*, -- user_id,active_count,last_start_time
                    tt.instance,
                    tt.delay
                FROM user_metrics m
                JOIN "user" u ON u.id = m.user_id
                JOIN tariff tt ON tt.id = u.tariff_id
            ),
            first_pending_task AS (
                SELECT
                    t.id, t.user_id
                FROM task t
                WHERE t.user_id = :user_id
                  AND t.deleted = false
                  AND t.status = 1 -- Pending
                ORDER BY t.id
                LIMIT 1
            )
            SELECT
                CASE WHEN active_count >= instance
                    THEN
                        last_start_time + ((m.delay + 100) || ' seconds')::interval -- todo через сколько секунд закончится первый активный кодинг (expectTime в task.meta при старте транскода. Да и при Progress обновлять)
                    ELSE
                        last_start_time + (m.delay || ' seconds')::interval
                END as will_start_at
            FROM first_pending_task t
            JOIN user_metrics_tariff m ON t.user_id = m.user_id
        SQL;

        $date = $conn->executeQuery($sql, ['user_id' => $userId->toRfc4122()])->fetchOne();

        return $date ? new DateTimeImmutable($date) : null;
    }
}
