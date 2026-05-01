<?php
declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Application\Logging\LogServiceInterface;
use App\Application\Query\GetStorageQuery;
use App\Application\QueryHandler\QueryBus;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
readonly class StorageController
{
    public function __construct(
        private LogServiceInterface $logService,
        private QueryBus $queryBus,
    ) {
    }

    /**
     * Secure redirect to a user's poster or video file
     */
    #[Route('/storage/{key}', name: 'storage', requirements: ['key' => '.+'], methods: ['GET'])]
    public function index(string $key): Response
    {
        try {
            return $this->queryBus->query(
                new GetStorageQuery($key)
            );
        } catch (\Throwable $e) {
            $this->logService->log('storage', 'index', null, LogLevel::CRITICAL, 'Fail', [
                'message' => $e->getMessage(),
            ]);

            return new Response('Not Found', Response::HTTP_NOT_FOUND);
        }
    }
}
