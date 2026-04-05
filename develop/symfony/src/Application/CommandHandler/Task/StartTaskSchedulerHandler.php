<?php
declare(strict_types=1);

namespace App\Application\CommandHandler\Task;

use App\Application\Command\Task\StartTaskScheduler;
use App\Application\Command\Task\TranscodeVideo;
use App\Application\Event\StartTaskSchedulerFail;
use App\Application\Event\StartTaskSchedulerStart;
use App\Application\Event\StartTaskSchedulerSuccess;
use App\Application\Logging\LogServiceInterface;
use App\Application\Query\Repository\ScheduledTaskReadRepositoryInterface;
use App\Infrastructure\Task\TaskCancellationTrigger;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(bus: 'messenger.bus.command')]
final readonly class StartTaskSchedulerHandler
{
    public function __construct(
        private LogServiceInterface $logService,
        private ScheduledTaskReadRepositoryInterface $taskReadRepository,
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
        #[Autowire(service: 'messenger.bus.event')]
        private MessageBusInterface $eventBus,
        private TaskCancellationTrigger $cancellationTrigger,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    public function __invoke(StartTaskScheduler $command): void
    {
        try {
            $this->eventBus->dispatch(new StartTaskSchedulerStart());

            $scheduled = $this->taskReadRepository->getScheduled();
            $this->logService->log('task', 'scheduler', null, LogLevel::INFO, 'Tasks found for start', [
                'count' => count($scheduled),
            ]);

            foreach ($scheduled as $item) {
                $this->logService->log('task', 'scheduler', $item->taskId, LogLevel::INFO, 'Dispatching transcode', [
                    'userId' => $item->userId,
                    'videoId' => $item->videoId,
                ]);
                $this->cancellationTrigger->clear($item->taskId);
                $this->commandBus->dispatch(new TranscodeVideo($item));
            }

            $this->eventBus->dispatch(new StartTaskSchedulerSuccess(count($scheduled)));
        } catch (\Throwable $e) {
            $this->eventBus->dispatch(new StartTaskSchedulerFail($e->getMessage()));
            throw $e;
        }
    }
}
