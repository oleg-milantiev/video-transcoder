<?php
declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\Security\EncryptionServiceInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Asymmetric encryption service based on PHP's libsodium extension.
 *
 * Uses `crypto_box_seal` (anonymous sealed box): data is encrypted with the
 * configured public key and can only be decrypted with the matching secret key.
 *
 * Keys are base64-encoded strings loaded from environment variables at instantiation.
 * Only the public key needs to be present in production; the secret key is kept out
 * of prod so that even a full DB dump cannot be read without it.
 *
 * This service acts as a black box for the Application layer via EncryptionServiceInterface.
 */
final readonly class SodiumEncryptionService implements EncryptionServiceInterface
{
    private string $publicKey;
    private string $secretKey;

    public function __construct(
        #[Autowire('%env(SODIUM_PUBLIC_KEY)%')]
        string $sodiumPublicKey,
        #[Autowire('%env(SODIUM_PRIVATE_KEY)%')]
        string $sodiumPrivateKey,
    ) {
        $this->publicKey = $sodiumPublicKey;
        $this->secretKey = $sodiumPrivateKey;
    }

    /**
     * Encrypt plaintext with the configured public key (sealed anonymous box).
     *
     * @param string $plaintext Data to encrypt
     * @return string Base64-encoded ciphertext
     * @throws RuntimeException on invalid key encoding or encryption failure
     */
    public function encrypt(string $plaintext): string
    {
        if ($this->publicKey === 'changeme' || $this->publicKey === '') {
            throw new RuntimeException('SODIUM_PUBLIC_KEY is not configured.');
        }

        $publicKey = base64_decode($this->publicKey, true);
        if ($publicKey === false) {
            throw new RuntimeException('Invalid base64-encoded public key.');
        }

        return base64_encode(sodium_crypto_box_seal($plaintext, $publicKey));
    }

    /**
     * Decrypt a sealed ciphertext using the configured key pair.
     *
     * @param string $base64Ciphertext Base64-encoded ciphertext produced by encrypt()
     * @return string Decrypted plaintext
     * @throws RuntimeException when decryption fails (wrong key or corrupted data)
     */
    public function decrypt(string $base64Ciphertext): string
    {
        if ($this->secretKey === 'changeme' || $this->secretKey === '') {
            throw new RuntimeException('SODIUM_PRIVATE_KEY is not configured.');
        }

        $ciphertext = base64_decode($base64Ciphertext, true);
        $publicKey = base64_decode($this->publicKey, true);
        $secretKey = base64_decode($this->secretKey, true);

        if ($ciphertext === false || $publicKey === false || $secretKey === false) {
            throw new RuntimeException('Invalid base64-encoded input.');
        }

        $keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey($secretKey, $publicKey);
        $plaintext = sodium_crypto_box_seal_open($ciphertext, $keypair);

        if ($plaintext === false) {
            throw new RuntimeException('Decryption failed: invalid key or corrupted ciphertext.');
        }

        return $plaintext;
    }

    /**
     * Generate a new asymmetric key pair (static utility, not part of interface).
     *
     * @return array{publicKey: string, secretKey: string} base64-encoded keys
     */
    public static function generateKeyPair(): array
    {
        $keypair = sodium_crypto_box_keypair();

        return [
            'publicKey' => base64_encode(sodium_crypto_box_publickey($keypair)),
            'secretKey' => base64_encode(sodium_crypto_box_secretkey($keypair)),
        ];
    }
}
