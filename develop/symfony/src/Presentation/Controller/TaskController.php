<?php

namespace App\Presentation\Controller;

use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Infrastructure\Security\Voter\VideoAccessVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TaskController extends AbstractController
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository,
        private readonly VideoRepositoryInterface $videoRepository,
        private readonly StorageInterface $storage,
        private readonly Security $security,
    ) {
    }

    #[Route('/task/{id}/download', name: 'task_download', requirements: ['id' => '\\d+'])]
    public function download(int $id): Response
    {
        $task = $this->taskRepository->findById($id);
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

        return $this->redirect($this->storage->getUrl($output));
    }
}
