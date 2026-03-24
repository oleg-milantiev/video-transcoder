<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Console;

use App\Application\Command\Task\StartTaskScheduler;
use App\Application\Logging\LogServiceInterface;
use App\Application\Service\Maintenance\DeletedMediaCleanupService;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Presentation\Console\MinuteCommand;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class MinuteCommandTest extends TestCase
{
    public function testExecuteDispatchesSchedulerAndRunsCleanup(): void
    {
        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(StartTaskScheduler::class))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $videoRepository = $this->createMock(VideoRepositoryInterface::class);
        $videoRepository->expects($this->once())->method('findDeletedVideoForCleanup')->willReturn([]);

        $taskRepository = $this->createMock(TaskRepositoryInterface::class);
        $taskRepository->expects($this->once())->method('findDeletedTaskForCleanup')->willReturn([]);

        $cleanupService = new DeletedMediaCleanupService(
            $videoRepository,
            $taskRepository,
            $this->createStub(StorageInterface::class),
            $this->createStub(LogServiceInterface::class),
        );

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())
            ->method('createLock')
            ->with('app:minute', 900)
            ->willReturn($lock);

        $command = new MinuteCommand(
            $commandBus,
            $cleanupService,
            $this->createStub(LoggerInterface::class),
            $lockFactory,
        );

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        // TODO заблокировано StorageInterface
//        $this->assertStringContainsString('Cleanup done: video candidates=0, task candidates=0, video files deleted=0, task files deleted=0.', $tester->getDisplay());
    }
}
