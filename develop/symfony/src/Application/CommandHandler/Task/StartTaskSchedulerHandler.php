<?php

namespace App\Application\CommandHandler\Task;

use App\Application\Command\Task\StartTaskScheduler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use App\Domain\Video\Repository\TaskRepositoryInterface;

#[AsMessageHandler]
final readonly class StartTaskSchedulerHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private TaskRepositoryInterface $taskRepository,
    ) {
    }

    public function __invoke(StartTaskScheduler $command): void
    {
        $this->logger->info('StartTaskScheduler invoked');

        $tasks = $this->taskRepository->getTasksForStart();
        $this->logger->info('Tasks found for start', ['count' => count($tasks)]);
    }
}

