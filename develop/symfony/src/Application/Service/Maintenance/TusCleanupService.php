<?php

declare(strict_types=1);

// TODO кажется, место ему в инфраструктуре / Tus
namespace App\Application\Service\Maintenance;

use Psr\Log\LoggerInterface;
use TusPhp\Tus\Server as TusServer;

final readonly class TusCleanupService
{
    public function __construct(
        private TusServer $tusServer,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function cleanupExpiredUploads(): array
    {
        $deleted = $this->tusServer->handleExpiration();

        foreach ($deleted as $fileMeta) {
            $this->logger->info('Expired tus upload deleted', [
                'name' => $fileMeta['name'] ?? null,
                'filePath' => $fileMeta['file_path'] ?? null,
            ]);
        }

        return $deleted;
    }
}
