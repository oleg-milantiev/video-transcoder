<?php
declare(strict_types=1);

namespace App\Presentation\Console;

use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Security\SodiumEncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

/**
 * Dev-only command: decrypts and displays Cloudflare header data for a given user.
 *
 * Requires CF_PRIVATE_KEY to be set in the environment.  This key is intentionally
 * absent from production deployments, so this command will refuse to run there.
 */
#[AsCommand(
    name: 'app:user:data',
    description: 'Decrypt and display detailed CF header data for a user (dev-only)',
)]
final class GetUserDataCommand extends Command
{
    public function __construct(
        private readonly SodiumEncryptionService $sodiumEncryptionService,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%env(CF_PUBLIC_KEY)%')]
        private readonly string $cfPublicKey,
        #[Autowire('%env(CF_PRIVATE_KEY)%')]
        private readonly string $cfPrivateKey,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('email', null, InputOption::VALUE_REQUIRED, 'User email address');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->cfPrivateKey === '' || $this->cfPrivateKey === 'changeme') {
            $output->writeln('<error>CF_PRIVATE_KEY is not configured. This command is dev-only and must not run in production.</error>');

            return Command::FAILURE;
        }

        $email = $input->getOption('email');
        if (!is_string($email) || $email === '') {
            $output->writeln('<error>Please provide --email option.</error>');

            return Command::FAILURE;
        }

        /** @var UserEntity|null $user */
        $user = $this->entityManager->getRepository(UserEntity::class)->findOneBy(['email' => $email]);
        if ($user === null) {
            $output->writeln('<error>User not found: ' . $email . '</error>');

            return Command::FAILURE;
        }

        $output->writeln('Email:      ' . $user->email);
        $output->writeln('ID:         ' . ($user->id?->toRfc4122() ?? 'N/A'));
        $output->writeln('Roles:      ' . implode(', ', $user->roles));
        $output->writeln('Created at: ' . $user->createdAt->format('Y-m-d H:i:s'));
        $output->writeln('Last login: ' . (isset($user->loginedAt) ? $user->loginedAt->format('Y-m-d H:i:s') : 'never'));

        $cf = $user->profile['cf'] ?? null;
        if (!is_string($cf)) {
            $output->writeln('CF data:    <info>not available</info>');

            return Command::SUCCESS;
        }

        try {
            $decrypted = $this->sodiumEncryptionService->decrypt($cf, $this->cfPublicKey, $this->cfPrivateKey);
            /** @var array<string, list<string>> $cfData */
            $cfData = json_decode($decrypted, true, 512, JSON_THROW_ON_ERROR);

            $output->writeln('CF headers:');
            foreach ($cfData as $key => $values) {
                $output->writeln(sprintf('  %s: %s', $key, implode(', ', (array)$values)));
            }
        } catch (JsonException $e) {
            $output->writeln('<error>Failed to parse decrypted CF data: ' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        } catch (Throwable $e) {
            $output->writeln('<error>Failed to decrypt CF data: ' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
