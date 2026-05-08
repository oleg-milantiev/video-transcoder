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
    description: 'Generate a sodium asymmetric key pair for encrypted data storage',
)]
final class GenerateKeyPairCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $keyPair = SodiumEncryptionService::generateKeyPair();

        $output->writeln('# Copy the lines below into your .env file:');
        $output->writeln('SODIUM_PUBLIC_KEY='.$keyPair['publicKey']);
        $output->writeln('SODIUM_PRIVATE_KEY='.$keyPair['secretKey']);
        $output->writeln('# IMPORTANT: SODIUM_PRIVATE_KEY must NOT be deployed to production.');

        return Command::SUCCESS;
    }
}
