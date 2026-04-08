<?php
declare(strict_types=1);

namespace App\Presentation\Console;

use App\Application\Logging\LogServiceInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:smoke:result', description: 'Process e2e smoke test result from STDIN (JSON or null)')]
final class SmokeResultCommand extends Command
{
    public function __construct(
        private readonly LogServiceInterface $logService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stream = $input instanceof StreamableInputInterface
            ? ($input->getStream() ?? STDIN)
            : STDIN;

        $raw = trim((string) stream_get_contents($stream));

        if ($raw === '' || $raw === 'null') {
            $this->logService->log('smoke', 'result', null, LogLevel::ERROR, 'Result file not found', [
                'status' => 'unknown',
            ]);

            return Command::FAILURE;
        }

        /** @var array{status?: string, failedTests?: list<mixed>}|null $data */
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            $this->logService->log('smoke', 'result', null, LogLevel::ERROR, 'Invalid result file format', [
                'status' => 'unknown',
            ]);

            return Command::FAILURE;
        }

        $status = $data['status'] ?? 'unknown';
        $failedTests = $data['failedTests'] ?? [];

        if ($status === 'passed' && $failedTests === []) {
            $this->logService->log('smoke', 'result', null, LogLevel::INFO, 'Smoke tests passed', [
                'status' => $status,
                'failedTests' => $failedTests,
            ]);

            return Command::SUCCESS;
        }

        $this->logService->log('smoke', 'result', null, LogLevel::ERROR, 'Smoke tests failed', [
            'status' => $status,
            'failedTests' => $failedTests,
        ]);

        return Command::FAILURE;
    }
}
