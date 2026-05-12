<?php
declare(strict_types=1);

namespace App\Presentation\Console;

use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:smoke:finish',
    description: 'Delete the isolated smoke-test users created by app:smoke:prepare. Pass --email once per user, or omit to auto-delete today\'s two users.',
)]
final class SmokeUserFinishCommand extends Command
{
    /** Matches prod-YYYYMMDD-free@test.com or prod-YYYYMMDD-premium@test.com */
    private const string EMAIL_PATTERN = '/^prod-\d{8}-(free|premium)@test\.com$/';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'email',
            null,
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'Smoke-test user email(s) to delete (repeatable). Omit to delete today\'s two users.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $emails */
        $emails = $input->getOption('email');

        if ($emails === []) {
            $dateSuffix = new \DateTimeImmutable()->format('Ymd');
            $emails = [
                "prod-{$dateSuffix}-free@test.com",
                "prod-{$dateSuffix}-premium@test.com",
            ];
        }

        $exitCode = Command::SUCCESS;

        foreach ($emails as $email) {
            if (!preg_match(self::EMAIL_PATTERN, $email)) {
                $output->writeln(sprintf(
                    '<error>Email "%s" does not match the smoke-test user mask (prod-YYYYMMDD-(free|premium)@test.com) — skipped.</error>',
                    $email,
                ));
                $exitCode = Command::FAILURE;
                continue;
            }

            /** @var UserEntity|null $user */
            $user = $this->entityManager->getRepository(UserEntity::class)->findOneBy(['email' => $email]);

            if ($user === null) {
                $output->writeln(sprintf('<info>User "%s" not found — nothing to delete.</info>', $email));
                continue;
            }

            $this->entityManager->remove($user);
            $this->entityManager->flush();

            $output->writeln(sprintf('Deleted smoke-test user: %s', $email));
        }

        return $exitCode;
    }
}
