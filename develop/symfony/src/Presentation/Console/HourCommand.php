<?php
declare(strict_types=1);

namespace App\Presentation\Console;

use App\Application\Service\Maintenance\TusCleanupService;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use Psr\Log\LoggerInterface;
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
        private readonly TusCleanupService $tusCleanupService,
        private readonly LoggerInterface $logger,
        private readonly LockFactory $lockFactory,
        private readonly VideoRepositoryInterface $videoRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info('app:hour started');
        $output->writeln('Attempting to acquire mutex...');

        $lock = $this->lockFactory->createLock('app:hour', self::MUTEX_TTL);
        $acquired = false;

        try {
            $acquired = $lock->acquire();
            if (!$acquired) {
                $this->logger->info('Another app:hour instance is already running, exiting.');
                $output->writeln('Another instance is already running, exiting.');

                return Command::SUCCESS;
            }

            $deleted = $this->tusCleanupService->cleanupExpiredUploads();
            $tusCount = count($deleted);
            $this->logger->info('app:hour tus cleanup finished', ['deletedCount' => $tusCount]);

            $expiredCount = $this->videoRepository->deleteExpiredVideosAndTasks();
            $this->logger->info('app:hour delete expired videos finished', ['deletedCount' => $expiredCount]);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->logger->error('app:hour failed: ' . $e->getMessage(), ['exception' => $e]);
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        } finally {
            if ($acquired) {
                try {
                    $lock->release();
                } catch (\Throwable $e) {
                    $this->logger->error('Failed to release app:hour mutex: ' . $e->getMessage(), ['exception' => $e]);
                }
            }
        }
    }
}
