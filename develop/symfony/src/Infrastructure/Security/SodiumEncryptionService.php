<?php
declare(strict_types=1);

namespace App\Infrastructure\Security;

use RuntimeException;

/**
 * Asymmetric encryption service based on PHP's libsodium extension.
 *
 * Uses `crypto_box_seal` (anonymous sealed box): data is encrypted with the
 * recipient's public key and can only be decrypted with the matching secret key.
 *
 * Key pairs are base64-encoded strings suitable for storage in environment
 * variables.  Only the public key needs to be present in production; the secret
 * key is kept out of prod so that even a full DB dump cannot be read without it.
 */
final readonly class SodiumEncryptionService
{
    /**
     * Generate a new asymmetric key pair.
     *
     * @return array{publicKey: string, secretKey: string} base64-encoded keys
     */
    public function generateKeyPair(): array
    {
        $keypair = sodium_crypto_box_keypair();

        return [
            'publicKey' => base64_encode(sodium_crypto_box_publickey($keypair)),
            'secretKey' => base64_encode(sodium_crypto_box_secretkey($keypair)),
        ];
    }

    /**
     * Encrypt plaintext with the recipient's public key (sealed anonymous box).
     *
     * @param string $plaintext      Data to encrypt
     * @param string $base64PublicKey Base64-encoded recipient public key
     *
     * @return string Base64-encoded ciphertext
     *
     * @throws RuntimeException on invalid key encoding
     */
    public function encrypt(string $plaintext, string $base64PublicKey): string
    {
        $publicKey = base64_decode($base64PublicKey, true);
        if ($publicKey === false) {
            throw new RuntimeException('Invalid base64-encoded public key.');
        }

        return base64_encode(sodium_crypto_box_seal($plaintext, $publicKey));
    }

    /**
     * Decrypt a sealed ciphertext using the recipient's key pair.
     *
     * @param string $base64Ciphertext Base64-encoded ciphertext produced by encrypt()
     * @param string $base64PublicKey  Base64-encoded public key
     * @param string $base64SecretKey  Base64-encoded secret key (dev/local only)
     *
     * @return string Decrypted plaintext
     *
     * @throws RuntimeException when decryption fails (wrong key or corrupted data)
     */
    public function decrypt(
        string $base64Ciphertext,
        string $base64PublicKey,
        string $base64SecretKey,
    ): string {
        $ciphertext = base64_decode($base64Ciphertext, true);
        $publicKey  = base64_decode($base64PublicKey, true);
        $secretKey  = base64_decode($base64SecretKey, true);

        if ($ciphertext === false || $publicKey === false || $secretKey === false) {
            throw new RuntimeException('Invalid base64-encoded input.');
        }

        $keypair  = sodium_crypto_box_keypair_from_secretkey_and_publickey($secretKey, $publicKey);
        $plaintext = sodium_crypto_box_seal_open($ciphertext, $keypair);

        if ($plaintext === false) {
            throw new RuntimeException('Decryption failed: invalid key or corrupted ciphertext.');
        }

        return $plaintext;
    }
}
