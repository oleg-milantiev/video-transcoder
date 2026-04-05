<?php
declare(strict_types=1);

namespace App\Presentation\Console;

use App\Application\Logging\LogServiceInterface;
use App\Application\Service\Task\DeletedTaskCleanupService;
use App\Application\Service\Video\DeletedVideoCleanupService;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;

#[AsCommand(name: 'app:day', description: 'Run every day from cron')]
final class DayCommand extends Command
{
    private const int MUTEX_TTL = 4000;

    public function __construct(
        private readonly LogServiceInterface $logService,
        private readonly LockFactory $lockFactory,
        private readonly DeletedVideoCleanupService $deletedVideoCleanupService,
        private readonly DeletedTaskCleanupService $deletedTaskCleanupService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ms = microtime(true);
        $this->logService->log('cron', 'day', null, LogLevel::INFO, 'Start');

        $lock = $this->lockFactory->createLock('app:day', self::MUTEX_TTL);
        $acquired = false;

        try {
            $acquired = $lock->acquire();
            if (!$acquired) {
                $this->logService->log('cron', 'day', null, LogLevel::DEBUG, 'Another instance is already running');

                return Command::SUCCESS;
            }

            $output->writeln('Running deleted media cleanup...');

            $videoResult = $this->deletedVideoCleanupService->cleanup();
            $taskResult = $this->deletedTaskCleanupService->cleanup();

            $this->logService->log('cron', 'day', null, LogLevel::INFO, 'Finish', [
                'time' => microtime(true) - $ms,
                'video' => [
                    'candidates' => $videoResult['candidates'],
                    'deleted' => $videoResult['filesDeleted'],
                ],
                'task' => [
                    'candidates' => $taskResult['candidates'],
                    'deleted' => $taskResult['filesDeleted'],
                ],
            ]);
        } catch (\Throwable $e) {
            $this->logService->log('cron', 'day', null, LogLevel::ERROR, 'Fail', [
                'message' => $e->getMessage(),
            ]);

            return Command::FAILURE;
        } finally {
            if ($acquired) {
                try {
                    $lock->release();

                    return Command::SUCCESS;
                } catch (\Throwable $e) {
                    $this->logService->log('cron', 'day', null, LogLevel::ERROR, 'Fail', [
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        return Command::FAILURE;
    }
}
