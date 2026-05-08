<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Security;

use App\Infrastructure\Security\SodiumEncryptionService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SodiumEncryptionServiceTest extends TestCase
{
    private const string TEST_PUBLIC_KEY = 'C7a5OawBVhVgn6E7X5TkRYAOa+lBN5VaZgVlnpAmUCw=';
    private const string TEST_PRIVATE_KEY = '04bGdD/HeL66V5wZBqa3AZGf3VIMMgODT75h2x2606Q=';

    private function makeService(
        string $publicKey = self::TEST_PUBLIC_KEY,
        string $privateKey = self::TEST_PRIVATE_KEY,
    ): SodiumEncryptionService {
        return new SodiumEncryptionService($publicKey, $privateKey);
    }

    public function testGenerateKeyPairReturnsTwoNonEmptyBase64Strings(): void
    {
        $keyPair = SodiumEncryptionService::generateKeyPair();

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
        $keyPair = SodiumEncryptionService::generateKeyPair();

        $publicKeyBytes = base64_decode($keyPair['publicKey'], true);
        $secretKeyBytes = base64_decode($keyPair['secretKey'], true);

        self::assertIsString($publicKeyBytes);
        self::assertIsString($secretKeyBytes);
        self::assertSame(SODIUM_CRYPTO_BOX_PUBLICKEYBYTES, strlen($publicKeyBytes));
        self::assertSame(SODIUM_CRYPTO_BOX_SECRETKEYBYTES, strlen($secretKeyBytes));
    }

    public function testEncryptDecryptRoundTrip(): void
    {
        $service = $this->makeService();
        $plaintext = 'Hello, sensitive data!';

        $ciphertext = $service->encrypt($plaintext);
        $decrypted = $service->decrypt($ciphertext);

        self::assertSame($plaintext, $decrypted);
    }

    public function testEncryptProducesValidBase64(): void
    {
        $service = $this->makeService();

        $ciphertext = $service->encrypt('test');

        self::assertNotFalse(base64_decode($ciphertext, true), 'Ciphertext must be valid base64');
    }

    public function testEncryptProducesDifferentOutputEachTime(): void
    {
        $service = $this->makeService();
        $plaintext = 'same input';

        $ct1 = $service->encrypt($plaintext);
        $ct2 = $service->encrypt($plaintext);

        // Sealed boxes include a random nonce, so ciphertexts must differ
        self::assertNotSame($ct1, $ct2);
    }

    public function testDecryptWithWrongKeyThrowsRuntimeException(): void
    {
        $keyPair1 = SodiumEncryptionService::generateKeyPair();
        $keyPair2 = SodiumEncryptionService::generateKeyPair();

        $service1 = $this->makeService($keyPair1['publicKey'], $keyPair1['secretKey']);
        $service2 = $this->makeService($keyPair2['publicKey'], $keyPair2['secretKey']);

        $ciphertext = $service1->encrypt('secret');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed');

        $service2->decrypt($ciphertext);
    }

    public function testEncryptWithInvalidPublicKeyThrowsRuntimeException(): void
    {
        $service = new SodiumEncryptionService('!!!not-valid-base64!!!', self::TEST_PRIVATE_KEY);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid base64-encoded public key.');

        $service->encrypt('data');
    }

    public function testDecryptWithInvalidBase64InputThrowsRuntimeException(): void
    {
        $service = $this->makeService();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid base64-encoded input.');

        $service->decrypt('!!!not-valid-base64!!!');
    }

    public function testRoundTripWithJsonPayload(): void
    {
        $service = $this->makeService();
        $data = ['cf-connecting-ip' => ['1.2.3.4'], 'cf-ipcountry' => ['US']];
        $json = json_encode($data, JSON_THROW_ON_ERROR);

        $ciphertext = $service->encrypt($json);
        $decrypted = $service->decrypt($ciphertext);

        self::assertSame($json, $decrypted);
        self::assertSame($data, json_decode($decrypted, true));
    }

    public function testEncryptThrowsWhenPublicKeyIsPlaceholder(): void
    {
        $service = new SodiumEncryptionService('changeme', self::TEST_PRIVATE_KEY);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SODIUM_PUBLIC_KEY is not configured.');

        $service->encrypt('data');
    }

    public function testDecryptThrowsWhenPrivateKeyIsPlaceholder(): void
    {
        $service = $this->makeService();
        $ciphertext = $service->encrypt('test');

        $service2 = new SodiumEncryptionService(self::TEST_PUBLIC_KEY, 'changeme');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SODIUM_PRIVATE_KEY is not configured.');

        $service2->decrypt($ciphertext);
    }
}
