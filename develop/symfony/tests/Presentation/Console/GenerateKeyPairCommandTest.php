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
        $command = new GenerateKeyPairCommand();
        $tester  = new CommandTester($command);

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $tester->getDisplay();
        self::assertStringContainsString('SODIUM_PUBLIC_KEY=', $output);
        self::assertStringContainsString('SODIUM_PRIVATE_KEY=', $output);
    }

    public function testExecuteOutputsValidBase64Keys(): void
    {
        $command = new GenerateKeyPairCommand();
        $tester  = new CommandTester($command);
        $tester->execute([]);

        $output = $tester->getDisplay();

        preg_match('/SODIUM_PUBLIC_KEY=(.+)/', $output, $pubMatch);
        preg_match('/SODIUM_PRIVATE_KEY=(.+)/', $output, $secMatch);

        self::assertNotEmpty($pubMatch[1] ?? '');
        self::assertNotEmpty($secMatch[1] ?? '');

        $pub = trim($pubMatch[1]);
        $sec = trim($secMatch[1]);

        self::assertNotFalse(base64_decode($pub, true), 'Public key must be valid base64');
        self::assertNotFalse(base64_decode($sec, true), 'Secret key must be valid base64');
    }

    public function testExecuteOutputsProductionWarning(): void
    {
        $command = new GenerateKeyPairCommand();
        $tester  = new CommandTester($command);
        $tester->execute([]);

        self::assertStringContainsString('NOT be deployed to production', $tester->getDisplay());
    }

    public function testGeneratedKeyPairCanEncryptAndDecrypt(): void
    {
        $command = new GenerateKeyPairCommand();
        $tester  = new CommandTester($command);
        $tester->execute([]);

        $output = $tester->getDisplay();

        preg_match('/SODIUM_PUBLIC_KEY=(.+)/', $output, $pubMatch);
        preg_match('/SODIUM_PRIVATE_KEY=(.+)/', $output, $secMatch);

        $pub = trim($pubMatch[1]);
        $sec = trim($secMatch[1]);

        $sodiumService = new SodiumEncryptionService($pub, $sec);
        $plaintext  = 'round-trip test';
        $ciphertext = $sodiumService->encrypt($plaintext);
        $decrypted = $sodiumService->decrypt($ciphertext);

        self::assertSame($plaintext, $decrypted);
    }
}
