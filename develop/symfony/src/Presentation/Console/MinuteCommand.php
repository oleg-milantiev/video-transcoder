<?php

namespace App\Presentation\Console;

use App\Application\Command\Task\StartTaskScheduler;
use App\Application\Service\Maintenance\DeletedMediaCleanupService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface as MessengerExceptionInterface;
use Symfony\Component\Lock\LockFactory;

#[AsCommand(name: 'app:minute', description: 'Run every minute from cron to schedule tasks.')]
final class MinuteCommand extends Command
{
    private const int MUTEX_TTL = 900; // 15 minutes in seconds

    public function __construct(
        #[Autowire(service: 'messenger.bus.command')]
        private readonly MessageBusInterface $commandBus,
        private readonly DeletedMediaCleanupService $deletedMediaCleanupService,
        private readonly LoggerInterface $logger,
        private readonly LockFactory $lockFactory,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info('app:minute started');
        $output->writeln('Attempting to acquire mutex...');

        $lock = $this->lockFactory->createLock('app:minute', self::MUTEX_TTL);
        $acquired = false;

        try {
            $acquired = $lock->acquire();
            if (!$acquired) {
                $this->logger->info('Another instance is already running, exiting.');
                $output->writeln('Another instance is already running, exiting.');
                return Command::SUCCESS;
            }

            $output->writeln('Dispatching StartTaskScheduler...');
            try {
                $this->commandBus->dispatch(new StartTaskScheduler());
                $this->logger->info('StartTaskScheduler dispatched');
                $output->writeln('Dispatched successfully.');
                return Command::SUCCESS;
            } catch (MessengerExceptionInterface $e) {
                $this->logger->error('Failed to dispatch StartTaskScheduler (messenger): ' . $e->getMessage(), ['exception' => $e]);
                $output->writeln('<error>Messenger error: ' . $e->getMessage() . '</error>');
                return Command::FAILURE;
            } catch (\Throwable $e) {
                $this->logger->error('Failed to dispatch StartTaskScheduler: ' . $e->getMessage(), ['exception' => $e]);
                $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
                return Command::FAILURE;
            }

            // TODO заблокировано долгом StorageInterface
            /*
            try {
                $output->writeln('Running deleted media cleanup...');
                $cleanupResult = $this->deletedMediaCleanupService->cleanup();
                $this->logger->info('Deleted media cleanup finished', $cleanupResult);
                if ($cleanupResult['videoCandidates'] > 0 || $cleanupResult['taskCandidates'] > 0) {
                    $output->writeln(
                        sprintf(
                            'Cleanup done: video candidates=%d, task candidates=%d, video files deleted=%d, task files deleted=%d.',
                            $cleanupResult['videoCandidates'],
                            $cleanupResult['taskCandidates'],
                            $cleanupResult['videoFilesDeleted'],
                            $cleanupResult['taskFilesDeleted'],
                        )
                    );
                }
            } catch ... TODO
            */

        } finally {
            if ($acquired) {
                try {
                    $lock->release();
                    $this->logger->info('Mutex released');
                } catch (\Throwable $e) {
                    // Log and continue — releasing a lock should not fail the command
                    $this->logger->error('Failed to release mutex: ' . $e->getMessage(), ['exception' => $e]);
                }
            }
        }
    }
}
