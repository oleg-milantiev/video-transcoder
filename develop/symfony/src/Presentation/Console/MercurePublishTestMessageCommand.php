<?php

declare(strict_types=1);

namespace App\Presentation\Console;

use App\Application\Command\Mercure\PublishMercureMessage;
use App\Application\DTO\MercureMessageDTO;
use App\Infrastructure\Security\MercureTokenService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\UuidV4;

#[AsCommand(
    name: 'app:mercure:test-publish',
    description: 'Publish a test Mercure message for user 123e4567-e89b-42d3-a456-426614174000.'
)]
final class MercurePublishTestMessageCommand extends Command
{
    private const string TEST_USER_UUID = '123e4567-e89b-42d3-a456-426614174000';

    public function __construct(
        #[Autowire(service: 'messenger.bus.command')]
        private readonly MessageBusInterface $commandBus,
        private readonly MercureTokenService $mercureTokenService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $userId = UuidV4::fromString(self::TEST_USER_UUID);
            $topic = $this->mercureTokenService->createUserTopic($userId);

            $message = new MercureMessageDTO(
                action: 'test',
                entity: 'user',
                id: $userId,
                payload: [
                    'message' => 'Test message from app:me:te',
                    'sentAt' => new \DateTimeImmutable()->format(DATE_ATOM),
                ],
            );

            $this->commandBus->dispatch(new PublishMercureMessage($message));
            $output->writeln('<info>Published test message to topic: ' . $topic . '</info>');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('<error>Failed to publish test Mercure message: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
