<?php
declare(strict_types=1);

namespace App\Application\QueryHandler;

use App\Application\DTO\PresetItemDTO;
use App\Application\DTO\TaskItemDTO;
use App\Application\DTO\VideoDetailsDTO;
use App\Application\DTO\VideoItemDTO;
use App\Application\Exception\VideoAccessDeniedException;
use App\Application\Exception\VideoNotFoundException;
use App\Application\Query\GetVideoDetailsQuery;
use App\Domain\Video\Repository\PresetRepositoryInterface;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Persistence\Doctrine\User\UserMapper;
use App\Infrastructure\Security\Voter\VideoAccessVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.command')]
final readonly class GetVideoDetailsHandler
{
    public function __construct(
        private VideoRepositoryInterface $videoRepository,
        private PresetRepositoryInterface $presetRepository,
        private TaskRepositoryInterface $taskRepository,
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

        /** @var UserEntity $userEntity */
        $userEntity = $this->security->getUser();
        $user = UserMapper::toDomain($userEntity);
        if (!$user) {
            throw new \RuntimeException('User not found');
        }

        $tariff = $user->tariff();
        if (!$tariff) {
            throw new \RuntimeException('User without tariff');
        }

        $videoItemDto = VideoItemDTO::fromDomain($video, $this->storage, $this->taskRepository);

        $presetDtos = array_map(
            static fn($preset) => PresetItemDTO::fromDomain($preset),
            $this->presetRepository->findByTariff($tariff),
        );

        // todo optimize preset load
        $tasks = $this->taskRepository->findByVideoId($video->id());
        $taskDtos = [];
        foreach ($tasks as $task) {
            $preset = $this->presetRepository->findById($task->presetId());
            if ($preset === null) {
                continue;
            }
            $taskDtos[] = TaskItemDTO::fromDomain($task, $video, $preset);
        }

        return VideoDetailsDTO::create($videoItemDto, $presetDtos, $taskDtos);
    }
}
