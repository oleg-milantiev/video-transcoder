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
    public function testNullStdinLogsFileMissingAndReturnsFailure(): void
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
                $this->callback(static fn (array $ctx): bool => $ctx['status'] === 'unknown'),
            );

        $tester = new CommandTester(new SmokeResultCommand($logService));
        $tester->setInputs(['null']);
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
    }

    public function testPassedJsonLogsInfoAndReturnsSuccess(): void
    {
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

        $tester = new CommandTester(new SmokeResultCommand($logService));
        $tester->setInputs([(string) json_encode(['status' => 'passed', 'failedTests' => []])]);
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testFailedJsonLogsErrorAndReturnsFailure(): void
    {
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

        $tester = new CommandTester(new SmokeResultCommand($logService));
        $tester->setInputs([(string) json_encode([
            'status' => 'failed',
            'failedTests' => ['tests/01.admin.login.js', 'tests/02.upload.video.js'],
        ])]);
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
    }

    public function testInvalidJsonLogsErrorAndReturnsFailure(): void
    {
        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->once())
            ->method('log')
            ->with(
                'smoke',
                'result',
                null,
                LogLevel::ERROR,
                'Invalid result file format',
                $this->callback(static fn (array $ctx): bool => $ctx['status'] === 'unknown'),
            );

        $tester = new CommandTester(new SmokeResultCommand($logService));
        $tester->setInputs(['not-valid-json']);
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
    }
}
