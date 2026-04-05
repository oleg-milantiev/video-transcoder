<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Console;

use App\Application\Logging\LogServiceInterface;
use App\Application\Service\Maintenance\TusCleanupService;
use App\Domain\Video\Repository\VideoRepositoryInterface;
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
}
