<?php

namespace App\Presentation\Controller;

use App\Application\Logging\LogServiceInterface;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Infrastructure\Security\Voter\VideoAccessVoter;
use Psr\Log\LogLevel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\UuidV4;

class TaskController extends AbstractController
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository,
        private readonly VideoRepositoryInterface $videoRepository,
        private readonly LogServiceInterface $logService,
        private readonly StorageInterface $storage,
        private readonly Security $security,
    ) {
    }

    #[Route('/task/{id}/download', name: 'task_download', requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function download(string $id): Response
    {
        try {
            $taskId = UuidV4::fromString($id);
        } catch (\Throwable) {
            throw $this->createNotFoundException('Task not found');
        }

        $task = $this->taskRepository->findById(Uuid::fromString($taskId->toRfc4122()));
        if (!$task) {
            throw $this->createNotFoundException('Task not found');
        }

        $video = $this->videoRepository->findById($task->videoId());
        if (!$video) {
            throw $this->createNotFoundException('Video not found');
        }

        if (!$this->security->isGranted(VideoAccessVoter::CAN_DOWNLOAD_TRANSCODE, $video)) {
            throw $this->createAccessDeniedException('Access denied');
        }

        if ($task->status() !== TaskStatus::COMPLETED) {
            throw $this->createNotFoundException('Task output is not ready');
        }

        $output = $task->meta()['output'] ?? null;
        if (!$output) {
            throw $this->createNotFoundException('Output file not found');
        }

        // TODO таки надо это на уровне abstractController getUser заменить!
        $downloadedByUserId = Uuid::fromString($this->getUser()->id->toRfc4122());
        $context = [
            'taskId' => $task->id()->toRfc4122(),
            'videoId' => $video->id()?->toRfc4122(),
            'output' => $output,
            'downloadedByUserId' => $downloadedByUserId?->toRfc4122(),
        ];
        $this->logService->log('task', $task->id(), LogLevel::INFO, 'Transcode result downloaded', $context);
        $this->logService->log('video', $video->id(), LogLevel::INFO, 'Video transcode result downloaded', $context);
        $this->logService->log('user', $downloadedByUserId, LogLevel::INFO, 'User downloaded transcode result', $context);

        return $this->redirect($this->storage->publicUrl($output));
    }
}
