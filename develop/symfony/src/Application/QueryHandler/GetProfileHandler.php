<?php
declare(strict_types=1);

namespace App\Application\QueryHandler;

use App\Application\DTO\ProfileDTO;
use App\Application\Query\GetProfileQuery;
use App\Domain\Video\Repository\StorageRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.command')]
final readonly class GetProfileHandler
{
    public function __construct(
        private StorageRepositoryInterface $storageRepository,
    ) {
    }

    public function __invoke(GetProfileQuery $query): ProfileDTO
    {
        return ProfileDTO::create(
            storageDelete24: $this->storageRepository->getDeletedIn24hSize($query->userId),
        );
    }
}
