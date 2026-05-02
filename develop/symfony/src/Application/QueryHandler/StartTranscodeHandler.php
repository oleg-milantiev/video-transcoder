<?php
declare(strict_types=1);

namespace App\Application\QueryHandler;

use App\Application\Command\Task\StartTaskScheduler;
use App\Application\DTO\TaskItemDTO;
use App\Application\Event\StartTranscodeFail;
use App\Application\Event\StartTranscodeStart;
use App\Application\Event\StartTranscodeSuccess;
use App\Application\Exception\HeightExceedsTariffException;
use App\Application\Exception\PresetHeightNotAvailableException;
use App\Application\Exception\PresetNotFoundException;
use App\Application\Exception\TaskCreationFailedException;
use App\Application\Exception\TranscodeAccessDeniedException;
use App\Application\Exception\UserNotFoundException;
use App\Application\Exception\VideoNotFoundException;
use App\Application\Logging\LogServiceInterface;
use App\Application\Query\StartTranscodeQuery;
use App\Domain\User\Exception\TariffNotFound;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Repository\PresetRepositoryInterface;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Infrastructure\Security\Voter\VideoAccessVoter;
use Psr\Log\LogLevel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

#[AsMessageHandler(bus: 'messenger.bus.command')]
final readonly class StartTranscodeHandler
{
    public function __construct(
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
        #[Autowire(service: 'messenger.bus.event')]
        private MessageBusInterface $eventBus,
        private VideoRepositoryInterface $videoRepository,
        private PresetRepositoryInterface $presetRepository,
        private TaskRepositoryInterface $taskRepository,
        private UserRepositoryInterface $userRepository,
        private LogServiceInterface $logService,
        private Security $security,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    public function __invoke(StartTranscodeQuery $query): TaskItemDTO
    {
        $this->eventBus->dispatch(
            new StartTranscodeStart(
                $query->uuid->toRfc4122(),
                $query->presetId->toRfc4122(),
                $query->userId->toRfc4122(),
            )
        );

        $video = $this->videoRepository->findById($query->uuid);
        if (!$video) {
            $this->eventBus->dispatch(
                new StartTranscodeFail(
                    'Video not found',
                    $query->uuid->toRfc4122(),
                    $query->presetId->toRfc4122(),
                    $query->userId->toRfc4122()
                )
            );
            throw new VideoNotFoundException('Video not found');
        }
        if (!isset($video->meta()['width'], $video->meta()['height'])) {
            $this->eventBus->dispatch(
                new StartTranscodeFail(
                    'Video meta (width & height) is empty',
                    $query->uuid->toRfc4122(),
                    $query->presetId->toRfc4122(),
                    $query->userId->toRfc4122()
                )
            );
            throw new VideoNotFoundException('Video meta not found');
        }
        $videoWidth = (int)$video->meta()['width'];
        $videoHeight = (int)$video->meta()['height'];
        if ($videoWidth <= 0 || $videoHeight <= 0) {
            $this->eventBus->dispatch(
                new StartTranscodeFail(
                    'Video meta (width & height) is invalid',
                    $query->uuid->toRfc4122(),
                    $query->presetId->toRfc4122(),
                    $query->userId->toRfc4122()
                )
            );
            throw new VideoNotFoundException('Video meta is invalid');
        }

        $user = $this->userRepository->findById($query->userId);
        if (!$user) {
            $this->eventBus->dispatch(
                new StartTranscodeFail(
                    'User not found',
                    $query->uuid->toRfc4122(),
                    $query->presetId->toRfc4122(),
                    $query->userId->toRfc4122()
                )
            );
            throw new UserNotFoundException('User not found');
        }

        if (!$this->security->isGranted(VideoAccessVoter::CAN_START_TRANSCODE, $video)) {
            $this->eventBus->dispatch(
                new StartTranscodeFail(
                    'Access denied',
                    $query->uuid->toRfc4122(),
                    $query->presetId->toRfc4122(),
                    $query->userId->toRfc4122()
                )
            );
            throw new TranscodeAccessDeniedException('Access denied');
        }

        $preset = $this->presetRepository->findById($query->presetId);
        if (!$preset) {
            $this->eventBus->dispatch(
                new StartTranscodeFail(
                    'Preset not found',
                    $query->uuid->toRfc4122(),
                    $query->presetId->toRfc4122(),
                    $query->userId->toRfc4122()
                )
            );
            throw new PresetNotFoundException('Preset not found');
        }

        $bitrate = $preset->bitrate()->bitrateForHeight($query->height);
        if ($bitrate === null) {
            $this->eventBus->dispatch(
                new StartTranscodeFail(
                    'Height not available in preset',
                    $query->uuid->toRfc4122(),
                    $query->presetId->toRfc4122(),
                    $query->userId->toRfc4122()
                )
            );
            throw new PresetHeightNotAvailableException(
                sprintf('Height %d is not available in the preset', $query->height)
            );
        }

        $tariff = $user->tariff();
        if (!$tariff) {
            $this->eventBus->dispatch(
                new StartTranscodeFail(
                    'User without tariff',
                    $query->uuid->toRfc4122(),
                    $query->presetId->toRfc4122(),
                    $query->userId->toRfc4122()
                )
            );
            throw new TariffNotFound('Tariff not found');
        }

        if ($query->height > $tariff->maxHeight()->value()) {
            $this->eventBus->dispatch(
                new StartTranscodeFail(
                    'Height exceeds tariff',
                    $query->uuid->toRfc4122(),
                    $query->presetId->toRfc4122(),
                    $query->userId->toRfc4122()
                )
            );
            throw new HeightExceedsTariffException(
                sprintf('Height %d exceeds tariff maximum %d', $query->height, $tariff->maxHeight()->value())
            );
        }

        $ratio = $videoWidth > $videoHeight ? $videoWidth / $videoHeight : $videoHeight / $videoWidth;
        $rawWidth = (int)round($ratio * $query->height);
        $outputWidth = $rawWidth % 2 === 0 ? $rawWidth : $rawWidth + 1;

        try {
            $task = $this->taskRepository->findForTranscode($video->id(), $preset->id(), $user->id(), $query->height);

            if ($task instanceof Task) {
                $isRestart = true;
                $task->restart();
            } else {
                $isRestart = false;
                $task = Task::create($video->id(), $preset->id(), $user->id());
                $task->updateMeta([
                    'height' => $query->height,
                    'width' => $outputWidth,
                    'bitrate' => $bitrate,
                    'sizeExpected' => (int)($video->duration() * $bitrate / 8 * 1024 * 1024),
                ]);
            }

            $this->taskRepository->save($task);

            $context = [
                'taskId' => $task->id()?->toRfc4122(),
                'videoId' => $video->id()?->toRfc4122(),
                'presetId' => $preset->id()?->toRfc4122(),
                'height' => $task->heightNullable(),
                'userId' => $user->id()?->toRfc4122(),
                'isRestart' => $isRestart,
            ];
            $this->logService->log(
                'task',
                'transcode',
                $task->id(),
                LogLevel::INFO,
                'Transcode requested',
                array_diff_key($context, ['taskId' => 1])
            );
            $this->logService->log(
                'video',
                'transcode',
                $video->id(),
                LogLevel::INFO,
                'Transcode requested',
                array_diff_key($context, ['videoId' => 1])
            );
        } catch (Throwable $e) {
            $this->eventBus->dispatch(
                new StartTranscodeFail(
                    'Failed to create task',
                    $query->uuid->toRfc4122(),
                    $query->presetId->toRfc4122(),
                    $query->userId->toRfc4122()
                )
            );
            throw new TaskCreationFailedException('Failed to create task', previous: $e);
        }

        $this->commandBus->dispatch(new StartTaskScheduler());
        $this->eventBus->dispatch(
            new StartTranscodeSuccess(
                $task->id()->toRfc4122(),
                $query->uuid->toRfc4122(),
                $query->presetId->toRfc4122(),
                $query->userId->toRfc4122(),
            )
        );

        return TaskItemDTO::fromDomain($task, $video, $preset);
    }
}
