<?php
declare(strict_types=1);

namespace App\Infrastructure\Logging;

use App\Application\Logging\LogServiceInterface;
use App\Domain\Shared\ValueObject\Uuid;
use App\Infrastructure\Persistence\Doctrine\Preset\PresetRepository;
use App\Infrastructure\Persistence\Doctrine\Task\TaskRepository;
use App\Infrastructure\Persistence\Doctrine\User\TariffRepository;
use App\Infrastructure\Persistence\Doctrine\User\UserRepository;
use App\Infrastructure\Persistence\Doctrine\Video\VideoRepository;
use App\Presentation\Controller\Admin\PresetCrudController;
use App\Presentation\Controller\Admin\TariffCrudController;
use App\Presentation\Controller\Admin\TaskCrudController;
use App\Presentation\Controller\Admin\UserCrudController;
use App\Presentation\Controller\Admin\VideoCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Enriches log context with entity titles and admin detail links.
 *
 * Recognized context keys:
 *   videoId   → videoTitle, videoAdminUrl
 *   userId    → userEmail, userAdminUrl
 *   presetId  → presetTitle, presetAdminUrl
 *   taskId    → taskAdminUrl
 *   tariffId  → tariffTitle, tariffAdminUrl
 */
final readonly class EnrichContextLogDecorator implements LogServiceInterface
{
    public function __construct(
        private LogServiceInterface $inner,
        private AdminUrlGeneratorInterface $adminUrlGenerator,
        private VideoRepository $videoRepository,
        private UserRepository $userRepository,
        private PresetRepository $presetRepository,
        private TaskRepository $taskRepository,
        private TariffRepository $tariffRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function log(
        string $name,
        string $action,
        ?Uuid $objectId,
        string $level,
        string $text,
        array $context = []
    ): void {
        $context = $this->enrich($context);
        $this->inner->log($name, $action, $objectId, $level, $text, $context);
    }

    private function enrich(array $context): array
    {
        if (isset($context['videoId'])) {
            $context = $this->enrichVideo($context);
        }
        if (isset($context['userId'])) {
            $context = $this->enrichUser($context);
        }
        if (isset($context['presetId'])) {
            $context = $this->enrichPreset($context);
        }
        if (isset($context['taskId'])) {
            $context = $this->enrichTask($context);
        }
        if (isset($context['tariffId'])) {
            $context = $this->enrichTariff($context);
        }

        return $context;
    }

    private function enrichVideo(array $context): array
    {
        try {
            $entity = $this->videoRepository->find($context['videoId']);
            if ($entity !== null) {
                $context['videoTitle'] = $entity->title ?? (string)$context['videoId'];
                $context['videoAdminUrl'] = $this->buildDetailUrl(VideoCrudController::class, $context['videoId']);
            }
        } catch (Throwable $e) {
            $this->logger->warning('Failed to enrich video context', [
                'videoId' => $context['videoId'],
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }

        return $context;
    }

    private function buildDetailUrl(string $crudController, mixed $entityId): string
    {
        return $this->adminUrlGenerator
            ->unsetAll()
            ->setController($crudController)
            ->setAction(Crud::PAGE_DETAIL)
            ->setEntityId((string)$entityId)
            ->generateUrl();
    }

    private function enrichUser(array $context): array
    {
        try {
            $entity = $this->userRepository->find($context['userId']);
            if ($entity !== null) {
                $context['userEmail'] = $entity->email ?? (string)$context['userId'];
                $context['userAdminUrl'] = $this->buildDetailUrl(UserCrudController::class, $context['userId']);
            }
        } catch (Throwable $e) {
            $this->logger->warning('Failed to enrich user context', [
                'userId' => $context['userId'],
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }

        return $context;
    }

    private function enrichPreset(array $context): array
    {
        try {
            $entity = $this->presetRepository->find($context['presetId']);
            if ($entity !== null) {
                $context['presetTitle'] = $entity->title ?? (string)$context['presetId'];
                $context['presetAdminUrl'] = $this->buildDetailUrl(PresetCrudController::class, $context['presetId']);
            }
        } catch (Throwable $e) {
            $this->logger->warning('Failed to enrich preset context', [
                'presetId' => $context['presetId'],
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }

        return $context;
    }

    private function enrichTask(array $context): array
    {
        try {
            $entity = $this->taskRepository->find($context['taskId']);
            if ($entity !== null) {
                $context['taskAdminUrl'] = $this->buildDetailUrl(TaskCrudController::class, $context['taskId']);
            }
        } catch (Throwable $e) {
            $this->logger->warning('Failed to enrich task context', [
                'taskId' => $context['taskId'],
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }

        return $context;
    }

    private function enrichTariff(array $context): array
    {
        try {
            $entity = $this->tariffRepository->find($context['tariffId']);
            if ($entity !== null) {
                $context['tariffTitle'] = $entity->title ?? (string)$context['tariffId'];
                $context['tariffAdminUrl'] = $this->buildDetailUrl(TariffCrudController::class, $context['tariffId']);
            }
        } catch (Throwable $e) {
            $this->logger->warning('Failed to enrich tariff context', [
                'tariffId' => $context['tariffId'],
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }

        return $context;
    }
}
