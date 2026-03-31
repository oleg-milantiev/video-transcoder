<?php
declare(strict_types=1);

namespace App\Presentation\Console;

use App\Application\Service\Task\DeletedTaskCleanupService;
use App\Application\Service\Video\DeletedVideoCleanupService;
use Psr\Log\LoggerInterface;
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
        private readonly LoggerInterface $logger,
        private readonly LockFactory $lockFactory,
        private readonly DeletedVideoCleanupService $deletedVideoCleanupService,
        private readonly DeletedTaskCleanupService $deletedTaskCleanupService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info('app:day started');
        $output->writeln('Attempting to acquire mutex...');

        $lock = $this->lockFactory->createLock('app:day', self::MUTEX_TTL);
        $acquired = false;

        try {
            $acquired = $lock->acquire();
            if (!$acquired) {
                // todo оставить только log, убрать write во всех командах
                $this->logger->info('Another app:day instance is already running, exiting.');
                $output->writeln('Another instance is already running, exiting.');

                return Command::SUCCESS;
            }

            $output->writeln('Running deleted media cleanup...');

            $videoResult = $this->deletedVideoCleanupService->cleanup();
            $taskResult = $this->deletedTaskCleanupService->cleanup();

            $this->logger->info('Deleted media cleanup finished', [
                'videoResult' => $videoResult,
                'taskResult' => $taskResult,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('app:day failed: ' . $e->getMessage(), ['exception' => $e]);
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        } finally {
            if ($acquired) {
                try {
                    $lock->release();
                    $this->logger->info('app:day tus cleanup finished');
                    return Command::SUCCESS;
                } catch (\Throwable $e) {
                    $this->logger->error('Failed to release app:day mutex: ' . $e->getMessage(), ['exception' => $e]);
                }
            }
        }

        return Command::FAILURE;
    }
}
