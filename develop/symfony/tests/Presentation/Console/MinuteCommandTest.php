<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Console;

use App\Application\Command\Task\StartTaskScheduler;
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
    public function testExecuteDispatchesScheduler(): void
    {
        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(StartTaskScheduler::class))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

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
