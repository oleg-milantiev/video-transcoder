<?php
declare(strict_types=1);

// TODO кажется, место ему в инфраструктуре / Tus
namespace App\Application\Service\Maintenance;

use App\Application\Logging\LogServiceInterface;
use Psr\Log\LogLevel;
use TusPhp\Tus\Server as TusServer;

final readonly class TusCleanupService
{
    public function __construct(
        private TusServer $tusServer,
        private LogServiceInterface $logService,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function cleanupExpiredUploads(): array
    {
        $deleted = $this->tusServer->handleExpiration();

        foreach ($deleted as $fileMeta) {
            $this->logService->log('tus', 'cleanup', null, LogLevel::INFO, 'Expired tus upload deleted', [
                'name' => $fileMeta['name'] ?? null,
                'filePath' => $fileMeta['file_path'] ?? null,
            ]);
        }

        return $deleted;
    }
}
