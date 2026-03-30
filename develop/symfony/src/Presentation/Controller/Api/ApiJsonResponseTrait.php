<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api;

use Symfony\Component\HttpFoundation\JsonResponse;

trait ApiJsonResponseTrait
{
    /**
     * @param array<string, mixed> $data
     */
    private function apiSuccess(array $data, int $status = 200): JsonResponse
    {
        return new JsonResponse($data, $status);
    }

    /**
     * @param array<string, mixed> $details
     */
    private function apiError(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ], $status);
    }
}

