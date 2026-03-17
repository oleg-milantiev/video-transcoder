<?php

namespace App\Application\CommandHandler\Task;

use App\Application\Command\Task\StartTaskScheduler;
use App\Domain\Video\DTO\ScheduledTaskDTO;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Application\Command\Video\TranscodeVideo;

#[AsMessageHandler]
final readonly class StartTaskSchedulerHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private TaskRepositoryInterface $taskRepository,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    public function __invoke(StartTaskScheduler $command): void
    {
        $this->logger->info('StartTaskScheduler invoked');

        $scheduled = $this->taskRepository->getScheduled();
        $this->logger->info('Tasks found for start', ['count' => count($scheduled)]);

        foreach ($scheduled as $item) {
            $this->logger->info('Dispatching transcode for scheduled', [
                'taskId' => $item->taskId,
                'userId' => $item->userId,
                'videoId' => $item->videoId,
            ]);
            $this->messageBus->dispatch(new TranscodeVideo($item));
        }
    }
}

