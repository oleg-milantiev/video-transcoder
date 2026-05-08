<?php
declare(strict_types=1);

namespace App\Application\Security;

use RuntimeException;

/**
 * Application-level encryption service interface.
 *
 * Provides asymmetric encryption and key generation capabilities for sensitive data.
 * Keys are managed by the implementation (e.g., via environment variables).
 * The service acts as a black box: implementations handle all cryptographic details.
 */
interface EncryptionServiceInterface
{
    /**
     * Encrypt plaintext with the configured public key.
     *
     * @param string $plaintext Data to encrypt
     * @return string Base64-encoded ciphertext
     * @throws RuntimeException on encryption failure
     */
    public function encrypt(string $plaintext): string;

    /**
     * Decrypt a sealed ciphertext using the configured key pair.
     *
     * @param string $base64Ciphertext Base64-encoded ciphertext produced by encrypt()
     * @return string Decrypted plaintext
     * @throws RuntimeException when decryption fails (wrong key or corrupted data)
     */
    public function decrypt(string $base64Ciphertext): string;
}
