<?php

namespace App\Application\Query;

use App\Application\DTO\VideoDetailsDTO;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetVideoDetailsHandler
{
    public function __construct(
        private VideoRepositoryInterface $repository,
    ) {}

    public function __invoke(GetVideoDetailsQuery $query): VideoDetailsDTO
    {
        $video = $this->repository->findById($query->uuid);
        if (!$video) {
            throw new \DomainException('Video not found');
        }
        return VideoDetailsDTO::fromDomain($video);
    }
}
