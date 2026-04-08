<?php
declare(strict_types=1);

namespace App\Presentation\Console;

use App\Application\Logging\LogServiceInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:smoke:result', description: 'Process e2e smoke test result from artifacts directory')]
final class SmokeResultCommand extends Command
{
    private const string RESULT_FILE = 'test-results/.last-run.json';

    public function __construct(
        private readonly LogServiceInterface $logService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('artifacts-dir', InputArgument::REQUIRED, 'Path to the e2e artifacts directory');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $artifactsDir */
        $artifactsDir = $input->getArgument('artifacts-dir');

        $jsonFile = rtrim($artifactsDir, '/') . '/' . self::RESULT_FILE;

        if (!file_exists($jsonFile)) {
            $this->logService->log('smoke', 'result', null, LogLevel::ERROR, 'Result file not found', [
                'file' => $jsonFile,
                'status' => 'unknown',
            ]);

            return Command::FAILURE;
        }

        $content = file_get_contents($jsonFile);
        if ($content === false) {
            $this->logService->log('smoke', 'result', null, LogLevel::ERROR, 'Failed to read result file', [
                'file' => $jsonFile,
                'status' => 'unknown',
            ]);

            return Command::FAILURE;
        }

        /** @var array{status?: string, failedTests?: list<mixed>}|null $data */
        $data = json_decode($content, true);

        if (!is_array($data)) {
            $this->logService->log('smoke', 'result', null, LogLevel::ERROR, 'Invalid result file format', [
                'file' => $jsonFile,
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
