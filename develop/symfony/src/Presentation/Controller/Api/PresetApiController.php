<?php
declare(strict_types=1);

namespace App\Presentation\Controller\Api;

use App\Application\DTO\PresetItemDTO;
use App\Application\Logging\LogServiceInterface;
use App\Domain\Shared\ValueObject\Uuid;
use Psr\Log\LogLevel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Domain\Video\Repository\PresetRepositoryInterface;
use Throwable;

#[Route('/api/preset')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class PresetApiController extends AbstractController
{
    use ApiJsonResponseTrait;

    public function __construct(
        private readonly PresetRepositoryInterface $presetRepository,
        private readonly LogServiceInterface $logService,
    ) {
    }

    /**
     * Returns the list of transcoding presets available to the current user
     * based on their active tariff plan.
     * Results are ordered by preset title ascending.
     */
    #[Route('', name: 'api_preset_list', methods: ['GET'])]
    public function index(): Response
    {
        try {
            $userId = Uuid::fromString($this->getUser()->id->toRfc4122());
            $presets = $this->presetRepository->findForUser($userId);

            return $this->apiSuccess(
                array_map(
                    static fn($preset) => PresetItemDTO::fromDomain($preset),
                    $presets,
                )
            );
        } catch (Throwable $e) {
            $this->logService->log('preset', 'index', null, LogLevel::CRITICAL, 'Fail', [
                'message' => $e->getMessage(),
            ]);

            return $this->apiError('INTERNAL_ERROR', 'Failed to list presets', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
