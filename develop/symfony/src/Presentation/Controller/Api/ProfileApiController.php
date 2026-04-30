<?php
declare(strict_types=1);

namespace App\Presentation\Controller\Api;

use App\Application\Logging\LogServiceInterface;
use App\Application\Query\GetProfileQuery;
use App\Application\QueryHandler\QueryBus;
use App\Domain\Shared\ValueObject\Uuid;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use Psr\Log\LogLevel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

#[Route('/api/profile')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ProfileApiController extends AbstractController
{
    use ApiJsonResponseTrait;

    public function __construct(
        private readonly LogServiceInterface $logService,
        private readonly QueryBus $queryBus,
    ) {
    }

    /**
     * Returns the current user's profile data including tariff limits and storage usage.
     */
    #[Route('', name: 'api_profile', methods: ['GET'])]
    public function profile(): Response
    {
        /** @var UserEntity $userEntity */
        $userEntity = $this->getUser();
        $uuid = Uuid::fromString($userEntity->id->toRfc4122());

        try {
            return $this->apiSuccess(
                $this->queryBus->query(
                    new GetProfileQuery($uuid)
                )->toArray()
            );
        } catch (Throwable $e) {
            $this->logService->log('profile', 'index', $uuid, LogLevel::CRITICAL, 'Fail', [
                'message' => $e->getMessage(),
            ]);

            return $this->apiError(
                'INTERNAL_ERROR',
                'Failed to load profile data',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
