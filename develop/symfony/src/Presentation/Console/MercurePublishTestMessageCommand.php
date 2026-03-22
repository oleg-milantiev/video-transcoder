<?php

declare(strict_types=1);

namespace App\Presentation\Console;

use App\Infrastructure\Security\MercureTokenService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\UuidV4;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:mercure:test-publish',
    description: 'Publish a test Mercure message for user 123e4567-e89b-42d3-a456-426614174000.'
)]
final class MercurePublishTestMessageCommand extends Command
{
    private const string TEST_USER_UUID = '123e4567-e89b-42d3-a456-426614174000';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly MercureTokenService $mercureTokenService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $topic = $this->mercureTokenService->createUserTopic(UuidV4::fromString(self::TEST_USER_UUID));
            $publisherToken = $this->mercureTokenService->createPublisherTokenForTopic($topic);

            $payload = [
                'message' => 'Test message from app:mercure:test-publish',
                'userId' => self::TEST_USER_UUID,
                'sentAt' => new \DateTimeImmutable()->format(DATE_ATOM),
            ];

            $response = $this->httpClient->request('POST', $this->mercureTokenService->internalHubUrl(), [
                'headers' => [
                    'Authorization' => 'Bearer ' . $publisherToken,
                ],
                'body' => [
                    'topic' => $topic,
                    'data' => (string) json_encode($payload, JSON_THROW_ON_ERROR),
                ],
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 200 && $statusCode < 300) {
                $output->writeln('<info>Published test message to topic: ' . $topic . '</info>');
                return Command::SUCCESS;
            }

            $output->writeln('<error>Failed to publish message. Hub returned status ' . $statusCode . '.</error>');
            return Command::FAILURE;
        } catch (\Throwable $e) {
            $output->writeln('<error>Failed to publish test Mercure message: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
