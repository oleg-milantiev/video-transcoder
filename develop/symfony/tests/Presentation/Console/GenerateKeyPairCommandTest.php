<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Console;

use App\Infrastructure\Security\SodiumEncryptionService;
use App\Presentation\Console\GenerateKeyPairCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class GenerateKeyPairCommandTest extends TestCase
{
    public function testExecuteOutputsKeyPairEnvLines(): void
    {
        $command = new GenerateKeyPairCommand(new SodiumEncryptionService());
        $tester  = new CommandTester($command);

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $tester->getDisplay();
        self::assertStringContainsString('CF_PUBLIC_KEY=', $output);
        self::assertStringContainsString('CF_PRIVATE_KEY=', $output);
    }

    public function testExecuteOutputsValidBase64Keys(): void
    {
        $command = new GenerateKeyPairCommand(new SodiumEncryptionService());
        $tester  = new CommandTester($command);
        $tester->execute([]);

        $output = $tester->getDisplay();

        preg_match('/CF_PUBLIC_KEY=(.+)/', $output, $pubMatch);
        preg_match('/CF_PRIVATE_KEY=(.+)/', $output, $secMatch);

        self::assertNotEmpty($pubMatch[1] ?? '');
        self::assertNotEmpty($secMatch[1] ?? '');

        $pub = trim($pubMatch[1]);
        $sec = trim($secMatch[1]);

        self::assertNotFalse(base64_decode($pub, true), 'Public key must be valid base64');
        self::assertNotFalse(base64_decode($sec, true), 'Secret key must be valid base64');
    }

    public function testExecuteOutputsProductionWarning(): void
    {
        $command = new GenerateKeyPairCommand(new SodiumEncryptionService());
        $tester  = new CommandTester($command);
        $tester->execute([]);

        self::assertStringContainsString('NOT be deployed to production', $tester->getDisplay());
    }

    public function testGeneratedKeyPairCanEncryptAndDecrypt(): void
    {
        $sodiumService = new SodiumEncryptionService();
        $command = new GenerateKeyPairCommand($sodiumService);
        $tester  = new CommandTester($command);
        $tester->execute([]);

        $output = $tester->getDisplay();

        preg_match('/CF_PUBLIC_KEY=(.+)/', $output, $pubMatch);
        preg_match('/CF_PRIVATE_KEY=(.+)/', $output, $secMatch);

        $pub = trim($pubMatch[1]);
        $sec = trim($secMatch[1]);

        $plaintext  = 'round-trip test';
        $ciphertext = $sodiumService->encrypt($plaintext, $pub);
        $decrypted  = $sodiumService->decrypt($ciphertext, $pub, $sec);

        self::assertSame($plaintext, $decrypted);
    }
}
