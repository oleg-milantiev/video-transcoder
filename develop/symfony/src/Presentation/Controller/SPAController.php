<?php
declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Repository\TaskRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Security\ApiTokenService;
use App\Infrastructure\Security\MercureTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class SPAController extends AbstractController
{
    public function __construct(
        protected readonly ApiTokenService $tokenService,
        protected readonly MercureTokenService $mercureTokenService,
        protected readonly VideoRepositoryInterface $videoRepository,
        protected readonly TaskRepositoryInterface $taskRepository,
    ) {
    }

    protected function getSPA(): array
    {
        /** @var UserEntity $user */
        $user = $this->getUser();

        if ($user === null) {
            return [];
        }

        $userId = $user ? Uuid::fromString($user->id->toRfc4122()) : null;

        // Dummy IDs used to generate URL templates which are later replaced on the client
        $dummyUuid = '11111111-1111-4111-8111-111111111111';
        $dummyPresetId = '22222222-2222-4222-8222-222222222222';
        $dummyTaskId = '33333333-3333-4333-8333-333333333333';

        return [
            'user' => [
                'id' => $userId->toRfc4122(),
                'identifier' => $user->getUserIdentifier(),
            ],
            'token' => [
                'access' => $this->tokenService->createToken($userId, $user->getUserIdentifier()),
                'refresh' => $this->tokenService->createRefreshToken($userId, $user->getUserIdentifier()),
            ],
            'mercure' => [
                'hub' => $this->mercureTokenService->publicHubUrl(),
                'token' => $this->mercureTokenService->createSubscriberTokenForUser($userId),
                'topic' => $this->mercureTokenService->createUserTopic($userId),
            ],
            'route' => [
                'home' => $this->generateUrl('app_home'),
                'videoDetails' => str_replace($dummyUuid, '__UUID__', $this->generateUrl('video_details', ['uuid' => $dummyUuid])),
                'refreshToken' => $this->generateUrl('api_auth_refresh'),
                'upload' => $this->generateUrl('api_tus'),
                'video' => [
                    'list' => $this->generateUrl('api_video_list'),
                    'details' => str_replace($dummyUuid, '__UUID__', $this->generateUrl('api_video_details', ['id' => $dummyUuid])),
                    'transcode' => str_replace([$dummyUuid, $dummyPresetId], ['__UUID__', '__PRESET_ID__'], $this->generateUrl('api_video_transcode', ['id' => $dummyUuid, 'presetId' => $dummyPresetId])),
                    'delete' => str_replace($dummyUuid, '__UUID__', $this->generateUrl('api_video_delete', ['id' => $dummyUuid])),
                    'patch' => str_replace($dummyUuid, '__UUID__', $this->generateUrl('api_video_patch', ['id' => $dummyUuid])),
                ],
                'task' => [
                    'list' => $this->generateUrl('api_task_list'),
                    'cancel' => str_replace($dummyTaskId, '__TASK_ID__', $this->generateUrl('api_task_cancel', ['id' => $dummyTaskId])),
                    'download' => str_replace($dummyTaskId, '__TASK_ID__', $this->generateUrl('task_download', ['id' => $dummyTaskId])),
                ],
            ],
            'tariff' => [
                'title' => $user->tariff?->title,
                'delay' => $user->tariff?->delay,
                'instance' => $user->tariff?->instance,
                'videoDuration' => $user->tariff?->videoDuration,
                'videoSize' => $user->tariff?->videoSize,
                'width' => $user->tariff?->maxWidth,
                'height' => $user->tariff?->maxHeight,
                'storage' => [
                    'now' => $this->videoRepository->getStorageSize($userId) + $this->taskRepository->getStorageSize($userId),
                    'max' => (int)($user->tariff?->storageGb * 1024 * 1024 * 1024 ?? 0),
                    'hour' => $user->tariff?->storageHour,
                ],
            ],
        ];
    }
}
