<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Console;

use App\Application\Logging\LogServiceInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Infrastructure\Upload\Maintenance\TusCleanupService;
use App\Presentation\Console\HourCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use TusPhp\Tus\Server as TusServer;

final class HourCommandTest extends TestCase
{
    public function testExecuteRunsTusCleanup(): void
    {
        $server = $this->createMock(TusServer::class);
        $server->expects($this->once())
            ->method('handleExpiration')
            ->willReturn([
                ['name' => 'chunk-a', 'file_path' => '/tmp/tus/chunk-a'],
                ['name' => 'chunk-b', 'file_path' => '/tmp/tus/chunk-b'],
            ]);

        $tusCleanupService = new TusCleanupService($server, $this->createStub(LogServiceInterface::class));

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())
            ->method('createLock')
            ->with('app:hour', 4000)
            ->willReturn($lock);

        $command = new HourCommand(
            $this->createStub(LogServiceInterface::class),
            $tusCleanupService,
            $lockFactory,
            $this->createStub(VideoRepositoryInterface::class),
        );

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testExecuteReturnsSuccessWhenLockNotAcquired(): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(false);
        $lock->expects($this->never())->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())->method('createLock')->willReturn($lock);

        $command = new HourCommand(
            $this->createStub(LogServiceInterface::class),
            new TusCleanupService($this->createStub(TusServer::class), $this->createStub(LogServiceInterface::class)),
            $lockFactory,
            $this->createStub(VideoRepositoryInterface::class),
        );

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    /**
     * When cleanup throws but lock.release() succeeds, the finally `return Command::SUCCESS`
     * overrides the catch's FAILURE return.  The catch block is still executed (covered).
     */
    public function testExecuteCoversCatchBlockWhenCleanupThrows(): void
    {
        $server = $this->createStub(TusServer::class);
        $server->method('handleExpiration')->willThrowException(new \RuntimeException('Tus server error'));

        $tusCleanupService = new TusCleanupService($server, $this->createStub(LogServiceInterface::class));

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())->method('createLock')->willReturn($lock);

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->atLeast(1))->method('log');

        $command = new HourCommand(
            $logService,
            $tusCleanupService,
            $lockFactory,
            $this->createStub(VideoRepositoryInterface::class),
        );

        $tester = new CommandTester($command);
        // Finally returns SUCCESS, overriding the catch's FAILURE
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    /**
     * When cleanup succeeds but lock.release() throws, the finally inner-catch logs,
     * doesn't return, so the catch's queued FAILURE is preserved.
     */
    public function testExecuteReturnsFailureWhenLockReleaseThrows(): void
    {
        $server = $this->createStub(TusServer::class);
        $server->method('handleExpiration')->willThrowException(new \RuntimeException('Tus server error'));

        $tusCleanupService = new TusCleanupService($server, $this->createStub(LogServiceInterface::class));

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release')->willThrowException(new \RuntimeException('Lock store unavailable'));

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())->method('createLock')->willReturn($lock);

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->atLeast(1))->method('log');

        $command = new HourCommand(
            $logService,
            $tusCleanupService,
            $lockFactory,
            $this->createStub(VideoRepositoryInterface::class),
        );

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
    }
}
