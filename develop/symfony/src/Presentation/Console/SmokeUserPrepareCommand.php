<?php
declare(strict_types=1);

namespace App\Presentation\Console;

use App\Infrastructure\Persistence\Doctrine\User\TariffEntity;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:smoke:prepare',
    description: 'Prepare two isolated smoke-test users (prod-Ymd-free@test.com and prod-Ymd-premium@test.com). Deletes any existing ones and recreates them with fresh random passwords.',
)]
final class SmokeUserPrepareCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dateSuffix = (new DateTimeImmutable())->format('Ymd');
        $emailFree = "prod-{$dateSuffix}-free@test.com";
        $emailPremium = "prod-{$dateSuffix}-premium@test.com";

        /** @var TariffEntity|null $freeTariff */
        $freeTariff = $this->entityManager->getRepository(TariffEntity::class)->findOneBy(['title' => 'Free']);
        /** @var TariffEntity|null $premiumTariff */
        $premiumTariff = $this->entityManager->getRepository(TariffEntity::class)->findOneBy(['title' => 'Premium']);

        $passwordFree = $this->createOrReplaceUser($emailFree, $freeTariff);
        $passwordPremium = $this->createOrReplaceUser($emailPremium, $premiumTariff);

        $this->entityManager->flush();

        $output->writeln('TEST_EMAIL_FREE=' . $emailFree);
        $output->writeln('TEST_PASSWORD_FREE=' . $passwordFree);
        $output->writeln('TEST_EMAIL_PREMIUM=' . $emailPremium);
        $output->writeln('TEST_PASSWORD_PREMIUM=' . $passwordPremium);

        return Command::SUCCESS;
    }

    private function createOrReplaceUser(string $email, ?TariffEntity $tariff): string
    {
        /** @var UserEntity|null $existing */
        $existing = $this->entityManager->getRepository(UserEntity::class)->findOneBy(['email' => $email]);

        if ($existing !== null) {
            $this->entityManager->remove($existing);
            $this->entityManager->flush();
        }

        $password = $this->generatePassword();

        $user = new UserEntity();
        $user->email = $email;
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->tariff = $tariff;

        $this->entityManager->persist($user);

        return $password;
    }

    private function generatePassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $alphabetLength = strlen($alphabet);
        $result = '';

        while (strlen($result) < 8) {
            $bytes = random_bytes(16);

            for ($i = 0; $i < strlen($bytes) && strlen($result) < 8; $i++) {
                $result .= $alphabet[ord($bytes[$i]) % $alphabetLength];
            }
        }

        return $result;
    }
}
