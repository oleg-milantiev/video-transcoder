<?php
declare(strict_types=1);

namespace App\Application\Exception;

final class InvalidUploadUrlException extends QueryException
{
    public static function invalidUrl(string $url): self
    {
        return new self(sprintf('Invalid URL: %s', $url));
    }

    public static function invalidScheme(string $scheme): self
    {
        return new self(sprintf('URL scheme "%s" is not allowed. Only http and https are supported.', $scheme));
    }

    public static function blockedHost(string $host): self
    {
        return new self(sprintf('Host "%s" is not reachable from this server.', $host));
    }

    public static function downloadFailed(string $url): self
    {
        return new self(sprintf('Failed to download file from URL: %s', $url));
    }

    public static function tooLarge(int $actualBytes, int $maxBytes): self
    {
        return new self(
            sprintf(
                'Remote file size (%s) exceeds the allowed download limit (%s).',
                self::humanBytes($actualBytes),
                self::humanBytes($maxBytes),
            )
        );
    }

    // todo dry
    private static function humanBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024 * 1024), 1).' GB';
        }
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1).' MB';
        }

        return round($bytes / 1024, 1).' KB';
    }
}
