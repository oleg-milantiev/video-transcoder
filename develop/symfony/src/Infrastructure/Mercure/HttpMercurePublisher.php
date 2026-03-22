<?php

declare(strict_types=1);

namespace App\Infrastructure\Mercure;

use App\Application\DTO\MercureMessageDTO;
use App\Application\Service\Mercure\MercurePublisherInterface;
use App\Infrastructure\Security\MercureTokenService;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class HttpMercurePublisher implements MercurePublisherInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private MercureTokenService $mercureTokenService,
    ) {
    }

    public function publish(MercureMessageDTO $message): void
    {
        $topic = $this->mercureTokenService->createUserTopic($message->id);
        $publisherToken = $this->mercureTokenService->createPublisherTokenForTopic($topic);

        $data = [
            'action' => $message->action,
            'entity' => $message->entity,
            'id' => $message->id->toRfc4122(),
            'payload' => $message->payload,
        ];

        try {
            $response = $this->httpClient->request('POST', $this->mercureTokenService->internalHubUrl(), [
                'proxy' => null,
                'no_proxy' => 'mercure,localhost,127.0.0.1',
                'headers' => [
                    'Authorization' => 'Bearer ' . $publisherToken,
                ],
                'body' => [
                    'topic' => $topic,
                    'data' => (string) json_encode($data, JSON_THROW_ON_ERROR),
                ],
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new \RuntimeException(sprintf('Mercure publish failed with status %d.', $statusCode));
            }
        } catch (TransportExceptionInterface $exception) {
            throw new \RuntimeException('Mercure publish transport failed: ' . $exception->getMessage(), 0, $exception);
        }
    }
}

