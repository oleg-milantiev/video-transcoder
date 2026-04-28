<?php
declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Repository\StorageRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Security\ApiTokenService;
use App\Infrastructure\Security\MercureTokenService;
use DateTimeInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class SPAController extends AbstractController
{
    public function __construct(
        protected readonly ApiTokenService $tokenService,
        protected readonly MercureTokenService $mercureTokenService,
        protected readonly StorageRepositoryInterface $storageRepository,
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
            'user' => [
                'id' => $userId->toRfc4122(),
                'createdAt' => $user->createdAt->format(DateTimeInterface::ATOM),
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
            'tariff' => [
                'title' => $user->tariff?->title,
                'delay' => $user->tariff?->delay,
                'instance' => $user->tariff?->instance,
                'videoDuration' => $user->tariff?->videoDuration,
                'videoSize' => $user->tariff?->videoSize,
                'width' => $user->tariff?->maxWidth,
                'height' => $user->tariff?->maxHeight,
                'storage' => [
                    'now' => $this->storageRepository->getUsedStorageSize($userId),
                    'max' => (int)($user->tariff?->storageGb * 1024 * 1024 * 1024 ?? 0),
                    'hour' => $user->tariff?->storageHour,
                ],
            ],
        ];
    }
}
