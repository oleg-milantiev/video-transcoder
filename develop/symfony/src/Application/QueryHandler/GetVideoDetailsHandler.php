<?php
declare(strict_types=1);

namespace App\Application\QueryHandler;

use App\Application\DTO\PresetWithTaskDTO;
use App\Application\DTO\TaskInfoDTO;
use App\Application\DTO\VideoDetailsDTO;
use App\Application\Exception\VideoAccessDeniedException;
use App\Application\Exception\VideoNotFoundException;
use App\Application\Query\GetVideoDetailsQuery;
use App\Application\Query\Repository\VideoDetailsReadRepositoryInterface;
use App\Domain\User\Exception\TariffNotFound;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Domain\Video\ValueObject\TaskStatus;
use App\Infrastructure\Persistence\Doctrine\User\TariffMapper;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Security\Voter\VideoAccessVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.command')]
final readonly class GetVideoDetailsHandler
{
    public function __construct(
        private VideoRepositoryInterface $videoRepository,
        private VideoDetailsReadRepositoryInterface $videoDetailsReadRepository,
        private StorageInterface $storage,
        private Security $security,
    ) {}

    public function __invoke(GetVideoDetailsQuery $query): VideoDetailsDTO
    {
        $video = $this->videoRepository->findById($query->uuid);
        if (!$video) {
            throw new VideoNotFoundException('Video not found');
        }

        if (!$this->security->isGranted(VideoAccessVoter::CAN_VIEW_DETAILS, $video)) {
            throw new VideoAccessDeniedException('Access denied');
        }

        $presetsWithTasks = [];
        foreach ($this->videoDetailsReadRepository->getDetailsByVideoId($video->id()) as $presetData) {
            $taskDto = null;
            if ($presetData['task']) {
                $taskDto = new TaskInfoDTO(
                    status: TaskStatus::tryFrom((int)$presetData['task']['status'])?->name ?? 'UNKNOWN',
                    progress: $presetData['task']['progress'],
                    createdAt: $presetData['task']['createdAt'],
                    waitingTariffInstance: $presetData['task']['waitingTariffInstance'],
                    waitingTariffDelay: $presetData['task']['waitingTariffDelay'],
                    willStartAt: $presetData['task']['willStartAt'],
                    id: $presetData['task']['id'],
                );
            }
            $presetsWithTasks[] = new PresetWithTaskDTO(
                id: $presetData['id'],
                title: $presetData['title'],
                expectedFileSize: $presetData['expectedFileSize'],
                task: $taskDto,
            );
        }

        /** @var UserEntity $user */
        $user = $this->security->getUser();
        if (!$user->tariff) {
            throw new TariffNotFound('Tariff not found');
        }
        $tariff = TariffMapper::toDomain($user->tariff);

        return VideoDetailsDTO::fromDomain($video, $presetsWithTasks, $this->storage, $tariff);
    }
}
