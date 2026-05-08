<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Console;

use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Security\SodiumEncryptionService;
use App\Presentation\Console\GetUserDataCommand;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

final class GetUserDataCommandTest extends TestCase
{
    private const string TEST_PUBLIC_KEY  = 'C7a5OawBVhVgn6E7X5TkRYAOa+lBN5VaZgVlnpAmUCw=';
    private const string TEST_PRIVATE_KEY = '04bGdD/HeL66V5wZBqa3AZGf3VIMMgODT75h2x2606Q=';

    private SodiumEncryptionService $sodiumService;

    protected function setUp(): void
    {
        $this->sodiumService = new SodiumEncryptionService();
    }

    private function makeUser(array $profile = []): UserEntity
    {
        $user = new UserEntity();
        $user->id = SymfonyUuid::fromString('11111111-1111-4111-8111-111111111111');
        $user->email = 'user@example.com';
        $user->roles = ['ROLE_USER'];
        $user->profile = $profile;

        return $user;
    }

    private function makeCommand(
        ?UserEntity $user,
        string $cfPublicKey = self::TEST_PUBLIC_KEY,
        string $cfPrivateKey = self::TEST_PRIVATE_KEY,
    ): GetUserDataCommand {
        /** @var EntityRepository<UserEntity> $repo */
        $repo = $this->createStub(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($user);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        return new GetUserDataCommand($this->sodiumService, $em, $cfPublicKey, $cfPrivateKey);
    }

    public function testReturnsFailureWhenPrivateKeyIsMissing(): void
    {
        $command = $this->makeCommand(null, self::TEST_PUBLIC_KEY, '');
        $tester  = new CommandTester($command);

        $exitCode = $tester->execute(['--email' => 'user@example.com']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('CF_PRIVATE_KEY is not configured', $tester->getDisplay());
    }

    public function testReturnsFailureWhenPrivateKeyIsPlaceholder(): void
    {
        $command = $this->makeCommand(null, self::TEST_PUBLIC_KEY, 'changeme');
        $tester  = new CommandTester($command);

        $exitCode = $tester->execute(['--email' => 'user@example.com']);

        self::assertSame(Command::FAILURE, $exitCode);
    }

    public function testReturnsFailureWhenEmailOptionMissing(): void
    {
        $command = $this->makeCommand(null);
        $tester  = new CommandTester($command);

        $exitCode = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('--email', $tester->getDisplay());
    }

    public function testReturnsFailureWhenUserNotFound(): void
    {
        $command = $this->makeCommand(null);
        $tester  = new CommandTester($command);

        $exitCode = $tester->execute(['--email' => 'nobody@example.com']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('User not found', $tester->getDisplay());
    }

    public function testReturnsSuccessWithNoCfDataWhenProfileEmpty(): void
    {
        $command = $this->makeCommand($this->makeUser([]));
        $tester  = new CommandTester($command);

        $exitCode = $tester->execute(['--email' => 'user@example.com']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('CF data', $tester->getDisplay());
        self::assertStringContainsString('not available', $tester->getDisplay());
    }

    public function testDecryptsAndDisplaysCfHeaders(): void
    {
        $cfData = [
            'cf-connecting-ip' => ['203.0.113.42'],
            'cf-ipcountry'     => ['DE'],
            'cf-ray'           => ['abc123-FRA'],
        ];
        $encrypted = $this->sodiumService->encrypt(
            json_encode($cfData, JSON_THROW_ON_ERROR),
            self::TEST_PUBLIC_KEY,
        );

        $user = $this->makeUser(['cf' => $encrypted]);
        $command = $this->makeCommand($user);
        $tester  = new CommandTester($command);

        $exitCode = $tester->execute(['--email' => 'user@example.com']);
        $output   = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('CF headers:', $output);
        self::assertStringContainsString('cf-connecting-ip', $output);
        self::assertStringContainsString('203.0.113.42', $output);
        self::assertStringContainsString('cf-ipcountry', $output);
        self::assertStringContainsString('DE', $output);
    }

    public function testOutputsUserBasicInfo(): void
    {
        $user = $this->makeUser([]);
        $user->loginedAt = new DateTimeImmutable('2025-01-15 10:30:00');

        $command = $this->makeCommand($user);
        $tester  = new CommandTester($command);
        $tester->execute(['--email' => 'user@example.com']);

        $output = $tester->getDisplay();
        self::assertStringContainsString('user@example.com', $output);
        self::assertStringContainsString('11111111-1111-4111-8111-111111111111', $output);
        self::assertStringContainsString('ROLE_USER', $output);
    }

    public function testReturnsFailureWhenDecryptionFailsWithWrongKey(): void
    {
        // Encrypt with the test key pair
        $encrypted = $this->sodiumService->encrypt('secret', self::TEST_PUBLIC_KEY);

        // Generate a different key pair for decryption
        $wrongKeyPair  = $this->sodiumService->generateKeyPair();
        $command = $this->makeCommand(
            $this->makeUser(['cf' => $encrypted]),
            $wrongKeyPair['publicKey'],
            $wrongKeyPair['secretKey'],
        );
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['--email' => 'user@example.com']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Failed to decrypt', $tester->getDisplay());
    }
}
