<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Security;

use App\Infrastructure\Security\SodiumEncryptionService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SodiumEncryptionServiceTest extends TestCase
{
    private SodiumEncryptionService $service;

    protected function setUp(): void
    {
        $this->service = new SodiumEncryptionService();
    }

    public function testGenerateKeyPairReturnsTwoNonEmptyBase64Strings(): void
    {
        $keyPair = $this->service->generateKeyPair();

        self::assertArrayHasKey('publicKey', $keyPair);
        self::assertArrayHasKey('secretKey', $keyPair);
        self::assertNotEmpty($keyPair['publicKey']);
        self::assertNotEmpty($keyPair['secretKey']);

        // Verify they are valid base64
        self::assertNotFalse(base64_decode($keyPair['publicKey'], true), 'publicKey must be valid base64');
        self::assertNotFalse(base64_decode($keyPair['secretKey'], true), 'secretKey must be valid base64');
    }

    public function testGenerateKeyPairReturnsCorrectKeySizes(): void
    {
        $keyPair = $this->service->generateKeyPair();

        $publicKeyBytes = base64_decode($keyPair['publicKey'], true);
        $secretKeyBytes = base64_decode($keyPair['secretKey'], true);

        self::assertIsString($publicKeyBytes);
        self::assertIsString($secretKeyBytes);
        self::assertSame(SODIUM_CRYPTO_BOX_PUBLICKEYBYTES, strlen($publicKeyBytes));
        self::assertSame(SODIUM_CRYPTO_BOX_SECRETKEYBYTES, strlen($secretKeyBytes));
    }

    public function testEncryptDecryptRoundTrip(): void
    {
        $keyPair = $this->service->generateKeyPair();
        $plaintext = 'Hello, sensitive data!';

        $ciphertext = $this->service->encrypt($plaintext, $keyPair['publicKey']);
        $decrypted  = $this->service->decrypt($ciphertext, $keyPair['publicKey'], $keyPair['secretKey']);

        self::assertSame($plaintext, $decrypted);
    }

    public function testEncryptProducesValidBase64(): void
    {
        $keyPair = $this->service->generateKeyPair();

        $ciphertext = $this->service->encrypt('test', $keyPair['publicKey']);

        self::assertNotFalse(base64_decode($ciphertext, true), 'Ciphertext must be valid base64');
    }

    public function testEncryptProducesDifferentOutputEachTime(): void
    {
        $keyPair = $this->service->generateKeyPair();
        $plaintext = 'same input';

        $ct1 = $this->service->encrypt($plaintext, $keyPair['publicKey']);
        $ct2 = $this->service->encrypt($plaintext, $keyPair['publicKey']);

        // Sealed boxes include a random nonce, so ciphertexts must differ
        self::assertNotSame($ct1, $ct2);
    }

    public function testDecryptWithWrongKeyThrowsRuntimeException(): void
    {
        $keyPair1 = $this->service->generateKeyPair();
        $keyPair2 = $this->service->generateKeyPair();

        $ciphertext = $this->service->encrypt('secret', $keyPair1['publicKey']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed');

        $this->service->decrypt($ciphertext, $keyPair2['publicKey'], $keyPair2['secretKey']);
    }

    public function testEncryptWithInvalidPublicKeyThrowsRuntimeException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid base64-encoded public key.');

        $this->service->encrypt('data', '!!!not-valid-base64!!!');
    }

    public function testDecryptWithInvalidBase64InputThrowsRuntimeException(): void
    {
        $keyPair = $this->service->generateKeyPair();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid base64-encoded input.');

        $this->service->decrypt('!!!not-valid-base64!!!', $keyPair['publicKey'], $keyPair['secretKey']);
    }

    public function testRoundTripWithJsonPayload(): void
    {
        $keyPair = $this->service->generateKeyPair();
        $data = ['cf-connecting-ip' => ['1.2.3.4'], 'cf-ipcountry' => ['US']];
        $json = json_encode($data, JSON_THROW_ON_ERROR);

        $ciphertext = $this->service->encrypt($json, $keyPair['publicKey']);
        $decrypted  = $this->service->decrypt($ciphertext, $keyPair['publicKey'], $keyPair['secretKey']);

        self::assertSame($json, $decrypted);
        self::assertSame($data, json_decode($decrypted, true));
    }
}
