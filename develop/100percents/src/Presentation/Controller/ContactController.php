<?php
declare(strict_types=1);

namespace App\Presentation\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/contact', name: 'contact', methods: ['POST'])]
class ContactController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Accepts a Contact Us submission from any visitor (no auth required).
     * Validates email and message, then logs them.
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $data = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->errorResponse('INVALID_JSON', 'Invalid request body', Response::HTTP_BAD_REQUEST);
        }

        $email   = isset($data['email'])   ? trim((string) $data['email'])   : '';
        $message = isset($data['message']) ? trim((string) $data['message']) : '';

        if ($email === '') {
            return $this->errorResponse('VALIDATION_ERROR', 'Email must not be empty', Response::HTTP_BAD_REQUEST);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->errorResponse('VALIDATION_ERROR', 'Invalid email address', Response::HTTP_BAD_REQUEST);
        }

        if ($message === '') {
            return $this->errorResponse('VALIDATION_ERROR', 'Message must not be empty', Response::HTTP_BAD_REQUEST);
        }

        if (mb_strlen($message) > 1000) {
            return $this->errorResponse('VALIDATION_ERROR', 'Message must not exceed 1000 characters', Response::HTTP_BAD_REQUEST);
        }

        $this->logger->info('Contact Us request', [
            'email'   => $email,
            'message' => $message,
        ]);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function errorResponse(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
