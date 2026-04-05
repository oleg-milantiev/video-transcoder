<?php
declare(strict_types=1);

namespace App\Presentation\Console;

use App\Application\Command\Task\StartTaskScheduler;
use App\Application\Logging\LogServiceInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Lock\LockFactory;

#[AsCommand(name: 'app:minute', description: 'Run every minute from cron to schedule tasks.')]
final class MinuteCommand extends Command
{
    private const int MUTEX_TTL = 900; // 15 minutes in seconds

    public function __construct(
        #[Autowire(service: 'messenger.bus.command')]
        private readonly MessageBusInterface $commandBus,
        private readonly LogServiceInterface $logService,
        private readonly LockFactory $lockFactory,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ms = microtime(true);
        $this->logService->log('cron', 'minute', null, LogLevel::INFO, 'Start');

        $lock = $this->lockFactory->createLock('app:minute', self::MUTEX_TTL);
        $acquired = false;

        try {
            $acquired = $lock->acquire();
            if (!$acquired) {
                $this->logService->log('cron', 'minute', null, LogLevel::DEBUG, 'Another instance is already running');

                return Command::SUCCESS;
            }

            try {
                $this->commandBus->dispatch(new StartTaskScheduler());
                $this->logService->log('cron', 'minute', null, LogLevel::INFO, 'Finish', [
                    'time' => microtime(true) - $ms,
                ]);

                return Command::SUCCESS;
            } catch (\Throwable $e) {
                $this->logService->log('cron', 'minute', null, LogLevel::ERROR, 'Fail', [
                    'message' => $e->getMessage(),
                ]);

                return Command::FAILURE;
            }
        } finally {
            if ($acquired) {
                try {
                    $lock->release();
                } catch (\Throwable $e) {
                    $this->logService->log('cron', 'minute', null, LogLevel::ERROR, 'Fail', [
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
