<?php

declare(strict_types=1);

namespace App\Tests\Application\Service\Maintenance;

use App\Application\Service\Maintenance\TusCleanupService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use TusPhp\Tus\Server as TusServer;

final class TusCleanupServiceTest extends TestCase
{
    public function testCleanupExpiredUploadsLogsDeletedItems(): void
    {
        $deleted = [
            ['name' => 'chunk-a', 'file_path' => '/tmp/tus/chunk-a'],
            ['name' => 'chunk-b', 'file_path' => '/tmp/tus/chunk-b'],
        ];

        $server = $this->createMock(TusServer::class);
        $server->expects($this->once())
            ->method('handleExpiration')
            ->willReturn($deleted);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))->method('info');

        $service = new TusCleanupService($server, $logger);

        $result = $service->cleanupExpiredUploads();

        $this->assertSame($deleted, $result);
    }
}
