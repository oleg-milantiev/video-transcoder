<?php

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

        return [
            'apiAccessToken' => $this->tokenService->createToken($userId, $user->getUserIdentifier()),
            'apiRefreshToken' => $this->tokenService->createRefreshToken($userId, $user->getUserIdentifier()),
            'mercureHubUrl' => $this->mercureTokenService->publicHubUrl(),
            'mercureSubscriberToken' => $this->mercureTokenService->createSubscriberTokenForUser($userId),
            'mercureTopic' => $this->mercureTokenService->createUserTopic($userId),
            'userId' => $userId->toRfc4122(),
            'maxVideoSize' => $user->tariff?->videoSize,
            'storage' => [
                'max' => (int)($user->tariff?->storageGb * 1024 * 1024 * 1024 ?? 0),
                'now' => $this->videoRepository->getStorageSize($userId) +
                    $this->taskRepository->getStorageSize($userId),
            ],
        ];
    }
}
