<?php
declare(strict_types=1);

namespace App\Presentation\Console;

use App\Infrastructure\Security\SodiumEncryptionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:sodium:generate-keypair',
    description: 'Generate a sodium asymmetric key pair for CF header encryption',
)]
final class GenerateKeyPairCommand extends Command
{
    public function __construct(
        private readonly SodiumEncryptionService $sodiumEncryptionService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $keyPair = $this->sodiumEncryptionService->generateKeyPair();

        $output->writeln('# Copy the lines below into your .env file:');
        $output->writeln('CF_PUBLIC_KEY=' . $keyPair['publicKey']);
        $output->writeln('CF_PRIVATE_KEY=' . $keyPair['secretKey']);
        $output->writeln('# IMPORTANT: CF_PRIVATE_KEY must NOT be deployed to production.');

        return Command::SUCCESS;
    }
}
