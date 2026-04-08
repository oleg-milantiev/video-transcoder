<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Console;

use App\Application\Logging\LogServiceInterface;
use App\Presentation\Console\SmokeResultCommand;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SmokeResultCommandTest extends TestCase
{
    public function testMissingFileLogsErrorAndReturnsFailure(): void
    {
        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->once())
            ->method('log')
            ->with(
                'smoke',
                'result',
                null,
                LogLevel::ERROR,
                'Result file not found',
                $this->callback(static fn (array $ctx): bool => isset($ctx['file'])),
            );

        $command = new SmokeResultCommand($logService);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['artifacts-dir' => '/non/existent/path']);

        $this->assertSame(Command::FAILURE, $exitCode);
    }

    public function testPassedTestsLogsInfoAndReturnsSuccess(): void
    {
        $tmpDir = sys_get_temp_dir() . '/smoke_test_' . uniqid('', true);
        mkdir($tmpDir . '/test-results', 0777, true);
        file_put_contents($tmpDir . '/test-results/.last-run.json', (string) json_encode([
            'status' => 'passed',
            'failedTests' => [],
        ]));

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->once())
            ->method('log')
            ->with(
                'smoke',
                'result',
                null,
                LogLevel::INFO,
                'Smoke tests passed',
                $this->callback(static fn (array $ctx): bool => $ctx['status'] === 'passed' && $ctx['failedTests'] === []),
            );

        $command = new SmokeResultCommand($logService);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['artifacts-dir' => $tmpDir]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        unlink($tmpDir . '/test-results/.last-run.json');
        rmdir($tmpDir . '/test-results');
        rmdir($tmpDir);
    }

    public function testFailedTestsLogsErrorAndReturnsFailure(): void
    {
        $tmpDir = sys_get_temp_dir() . '/smoke_test_' . uniqid('', true);
        mkdir($tmpDir . '/test-results', 0777, true);
        file_put_contents($tmpDir . '/test-results/.last-run.json', (string) json_encode([
            'status' => 'failed',
            'failedTests' => ['tests/01.admin.login.js', 'tests/02.upload.video.js'],
        ]));

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->once())
            ->method('log')
            ->with(
                'smoke',
                'result',
                null,
                LogLevel::ERROR,
                'Smoke tests failed',
                $this->callback(static fn (array $ctx): bool => $ctx['status'] === 'failed' && count($ctx['failedTests']) === 2),
            );

        $command = new SmokeResultCommand($logService);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['artifacts-dir' => $tmpDir]);

        $this->assertSame(Command::FAILURE, $exitCode);

        unlink($tmpDir . '/test-results/.last-run.json');
        rmdir($tmpDir . '/test-results');
        rmdir($tmpDir);
    }

    public function testInvalidJsonLogsErrorAndReturnsFailure(): void
    {
        $tmpDir = sys_get_temp_dir() . '/smoke_test_' . uniqid('', true);
        mkdir($tmpDir . '/test-results', 0777, true);
        file_put_contents($tmpDir . '/test-results/.last-run.json', 'not-valid-json');

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->once())
            ->method('log')
            ->with(
                'smoke',
                'result',
                null,
                LogLevel::ERROR,
                'Invalid result file format',
                $this->callback(static fn (array $ctx): bool => isset($ctx['file'])),
            );

        $command = new SmokeResultCommand($logService);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['artifacts-dir' => $tmpDir]);

        $this->assertSame(Command::FAILURE, $exitCode);

        unlink($tmpDir . '/test-results/.last-run.json');
        rmdir($tmpDir . '/test-results');
        rmdir($tmpDir);
    }
}
