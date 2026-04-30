<?php
declare(strict_types=1);

namespace App\Presentation\Controller\Api;

use App\Application\Logging\LogServiceInterface;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use Psr\Log\LogLevel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/contact', name: 'api_contact', methods: ['POST'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ContactApiController extends AbstractController
{
    use ApiJsonResponseTrait;

    public function __construct(
        private readonly LogServiceInterface $logService,
    ) {
    }

    /**
     * Submits a contact-us message from the authenticated user.
     * Validates that the message is non-empty and does not exceed 1000 characters,
     * then records it via the log service (routed to Telegram/Loki/DB depending on config).
     */
    public function __invoke(Request $request): Response
    {
        /** @var UserEntity $user */
        $user = $this->getUser();

        $data = json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $message = isset($data['message']) ? trim((string)$data['message']) : '';

        if ($message === '') {
            return $this->apiError('VALIDATION_ERROR', 'Message must not be empty', 400);
        }

        if (mb_strlen($message) > 1000) {
            return $this->apiError('VALIDATION_ERROR', 'Message must not exceed 1000 characters', 400);
        }

        $this->logService->log(
            'contact',
            'submit',
            null,
            LogLevel::INFO,
            'Contact Us request',
            [
                'email' => $user->getUserIdentifier(),
                'message' => $message,
            ]
        );

        return $this->apiSuccess(null, Response::HTTP_NO_CONTENT);
    }
}
