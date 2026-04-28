<?php
declare(strict_types=1);

namespace App\Application\QueryHandler;

use App\Application\DTO\PresetItemDTO;
use App\Application\DTO\VideoDetailsDTO;
use App\Application\DTO\VideoItemDTO;
use App\Application\Exception\VideoAccessDeniedException;
use App\Application\Exception\VideoNotFoundException;
use App\Application\Query\GetVideoDetailsQuery;
use App\Application\Response\VideoListResponse;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Repository\PresetRepositoryInterface;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Persistence\Doctrine\User\UserMapper;
use App\Infrastructure\Security\Voter\VideoAccessVoter;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.command')]
final readonly class GetVideoDetailsHandler
{
    // todo убрать. Использовать данные из frontend. Как это делается в списке видео
    private const int VIDEO_LIST_PAGE_LIMIT = 10;

    public function __construct(
        private VideoRepositoryInterface $videoRepository,
        private PresetRepositoryInterface $presetRepository,
        private TaskRepositoryInterface $taskRepository,
        private StorageInterface $storage,
        private Security $security,
    ) {
    }

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

        $tariff = $user->tariff();
        if (!$tariff) {
            throw new RuntimeException('User without tariff');
        }

        $userId = Uuid::fromString($userEntity->id->toRfc4122());

        $videoItemDto = VideoItemDTO::fromDomain($video, $this->storage, $this->taskRepository, $tariff);

        $presetDtos = array_map(
            static fn($preset) => PresetItemDTO::fromDomain($preset),
            $this->presetRepository->findByTariff($tariff),
        );

        $taskDtos = $this->taskRepository->getDetailsByVideoId($video->id());

        $page = $this->videoRepository->findVideoPage($video->id(), $userId, self::VIDEO_LIST_PAGE_LIMIT);
        $videoListResult = $this->videoRepository->findAllPaginated($page, self::VIDEO_LIST_PAGE_LIMIT, $userId);
        $videoList = VideoListResponse::fromDomain(
            $videoListResult->items,
            $videoListResult->total,
            $page,
            self::VIDEO_LIST_PAGE_LIMIT,
            $this->storage,
            $this->taskRepository,
        );

        return VideoDetailsDTO::create($videoItemDto, $presetDtos, $taskDtos, $videoList);
    }
}
