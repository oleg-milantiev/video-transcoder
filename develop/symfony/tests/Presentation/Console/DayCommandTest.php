<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Console;

use App\Application\Logging\LogServiceInterface;
use App\Application\Service\Task\DeletedTaskCleanupService;
use App\Application\Service\Video\DeletedVideoCleanupService;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Presentation\Console\DayCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

final class DayCommandTest extends TestCase
{
    private function makeVideoCleanup(?VideoRepositoryInterface $repo = null): DeletedVideoCleanupService
    {
        return new DeletedVideoCleanupService(
            $repo ?? $this->createStub(VideoRepositoryInterface::class),
            $this->createStub(StorageInterface::class),
            $this->createStub(LogServiceInterface::class),
        );
    }

    private function makeTaskCleanup(?TaskRepositoryInterface $repo = null): DeletedTaskCleanupService
    {
        return new DeletedTaskCleanupService(
            $repo ?? $this->createStub(TaskRepositoryInterface::class),
            $this->createStub(StorageInterface::class),
            $this->createStub(LogServiceInterface::class),
        );
    }

    public function testExecuteRunsCleanupAndReturnsSuccess(): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())
            ->method('createLock')
            ->with('app:day', 4000)
            ->willReturn($lock);

        $command = new DayCommand(
            $this->createStub(LogServiceInterface::class),
            $lockFactory,
            $this->makeVideoCleanup(),
            $this->makeTaskCleanup(),
        );

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Running deleted media cleanup', $tester->getDisplay());
    }

    public function testExecuteReturnsSuccessWhenLockNotAcquired(): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(false);
        $lock->expects($this->never())->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())->method('createLock')->willReturn($lock);

        $command = new DayCommand(
            $this->createStub(LogServiceInterface::class),
            $lockFactory,
            $this->makeVideoCleanup(),
            $this->makeTaskCleanup(),
        );

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    /**
     * When cleanup throws but lock.release() succeeds, the finally `return Command::SUCCESS`
     * overrides the catch's FAILURE return.  The catch block IS still executed (covered).
     */
    public function testExecuteCoversCatchBlockWhenCleanupThrows(): void
    {
        $videoRepo = $this->createStub(VideoRepositoryInterface::class);
        $videoRepo->method('findDeletedVideoForCleanup')->willThrowException(new \RuntimeException('Disk full'));

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())->method('createLock')->willReturn($lock);

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->atLeast(1))->method('log');

        $command = new DayCommand(
            $logService,
            $lockFactory,
            $this->makeVideoCleanup($videoRepo),
            $this->makeTaskCleanup(),
        );

        $tester = new CommandTester($command);
        // finally returns SUCCESS, overriding the catch's FAILURE return
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    /**
     * When cleanup succeeds but lock.release() throws, the finally inner-catch runs
     * without returning, so execution falls through to the last `return Command::FAILURE`.
     */
    public function testExecuteReturnsFailureWhenLockReleaseThrows(): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release')->willThrowException(new \RuntimeException('Lock store unavailable'));

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())->method('createLock')->willReturn($lock);

        $logService = $this->createMock(LogServiceInterface::class);
        $logService->expects($this->atLeast(1))->method('log');

        $command = new DayCommand(
            $logService,
            $lockFactory,
            $this->makeVideoCleanup(),
            $this->makeTaskCleanup(),
        );

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
    }
}
