<?php

namespace App\Presentation\Controller;

use App\Domain\Shared\ValueObject\Uuid;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Security\ApiTokenService;
use App\Infrastructure\Security\MercureTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class SPAController extends AbstractController
{
    public function __construct(
        protected readonly ApiTokenService $tokenService,
        protected readonly MercureTokenService $mercureTokenService,
    ) {
    }

    protected function getSPA(): array
    {
        /** @var UserEntity $user */
        $user = $this->getUser();
        $userId = $user ? Uuid::fromString($user->id->toRfc4122()) : null;

        return [
            'apiAccessToken' => $user ? $this->tokenService->createToken($userId, $user->getUserIdentifier()) : null,
            'apiRefreshToken' => $user ? $this->tokenService->createRefreshToken($userId, $user->getUserIdentifier()) : null,
            'mercureHubUrl' => $this->mercureTokenService->publicHubUrl(),
            'mercureSubscriberToken' => $user ? $this->mercureTokenService->createSubscriberTokenForUser($userId) : null,
            'mercureTopic' => $user ? $this->mercureTokenService->createUserTopic($userId) : null,
            'userId' => $user ? $userId->toRfc4122() : null,
            'maxVideoSize' => $user?->tariff?->videoSize,
        ];
    }
}
