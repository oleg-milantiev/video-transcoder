<?php
declare(strict_types=1);

namespace App\Presentation\Console;

use App\Application\Logging\LogServiceInterface;
use App\Application\Service\Maintenance\TusCleanupService;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;

#[AsCommand(name: 'app:hour', description: 'Run every hour from cron')]
final class HourCommand extends Command
{
    private const int MUTEX_TTL = 4000;

    public function __construct(
        private readonly LogServiceInterface $logService,
        private readonly TusCleanupService $tusCleanupService,
        private readonly LockFactory $lockFactory,
        private readonly VideoRepositoryInterface $videoRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ms = microtime(true);
        $this->logService->log('cron', 'hour', null, LogLevel::INFO, 'Start');

        $lock = $this->lockFactory->createLock('app:hour', self::MUTEX_TTL);
        $acquired = false;

        try {
            $acquired = $lock->acquire();
            if (!$acquired) {
                $this->logService->log('cron', 'hour', null, LogLevel::DEBUG, 'Another instance is already running');

                return Command::SUCCESS;
            }

            $deleted = $this->tusCleanupService->cleanupExpiredUploads();
            $expiredCount = $this->videoRepository->deleteExpiredVideosAndTasks();

            $this->logService->log('cron', 'hour', null, LogLevel::INFO, 'Finish', [
                'time' => microtime(true) - $ms,
                'tus' => [
                    'deleted' => count($deleted),
                ],
                'video' => [
                    'deleted' => $expiredCount,
                ],
            ]);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->logService->log('cron', 'hour', null, LogLevel::ERROR, 'Fail', [
                'message' => $e->getMessage(),
            ]);

            return Command::FAILURE;
        } finally {
            if ($acquired) {
                try {
                    $lock->release();

                    return Command::SUCCESS;
                } catch (\Throwable $e) {
                    $this->logService->log('cron', 'hour', null, LogLevel::ERROR, 'Fail', [
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
