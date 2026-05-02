<?php
declare(strict_types=1);

namespace App\Application\Service\Video;

use App\Application\Exception\InvalidUploadUrlException;

/**
 * Downloads a remote video file while enforcing size limits and basic SSRF guards.
 *
 * The downloaded file is placed in $urlUploadDir (same volume as the storage
 * driver so that FilesystemStorage::putFromPath() can rename() it cheaply).
 */
readonly class UrlVideoDownloader
{
    /** Absolute upper bound regardless of tariff — guards against abuse. */
    private const int|float HARD_CAP_BYTES = 4 * 1024 * 1024 * 1024; // 4 GB

    private const int CONNECT_TIMEOUT_S = 30;
    private const int DOWNLOAD_TIMEOUT_S = 600; // 10 minutes
    private const int READ_CHUNK_BYTES = 65536; // 64 KB
    private const string USER_AGENT = 'VideoTranscoder/1.0 (+https://github.com/oleg-milantiev/video-transcoder)';

    public function __construct(
        private string $urlUploadDir,
    ) {
    }

    /**
     * Downloads the file at $url, stopping at min($maxBytes, HARD_CAP_BYTES).
     * When $maxBytes is omitted only the built-in HARD_CAP_BYTES limit applies.
     *
     * @return array{path: string, filename: string, size: int}
     *
     * @throws InvalidUploadUrlException on bad URL, blocked host, or size exceeded
     */
    public function download(string $url, int $maxBytes = PHP_INT_MAX): array
    {
        $this->validateUrl($url);

        $effectiveMax = min($maxBytes, self::HARD_CAP_BYTES);

        // ── fast rejection via HEAD ──────────────────────────────────────────
        $headHeaders = $this->headRequest($url);
        if (isset($headHeaders['content-length'])) {
            $contentLength = (int)$headHeaders['content-length'];
            if ($contentLength > $effectiveMax) {
                throw InvalidUploadUrlException::tooLarge($contentLength, $effectiveMax);
            }
        }

        // ── stream download ──────────────────────────────────────────────────
        $filename = $this->extractFilename($url, $headHeaders);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $tmpPath = rtrim($this->urlUploadDir, '/')
            .'/'.uniqid('url_upload_', true)
            .($ext !== '' ? '.'.$ext : '');

        if (!is_dir($this->urlUploadDir)) {
            mkdir($this->urlUploadDir, 0755, true);
        }

        $this->streamDownload($url, $tmpPath, $effectiveMax);

        $size = filesize($tmpPath);
        if ($size === false) {
            @unlink($tmpPath);
            throw InvalidUploadUrlException::downloadFailed($url);
        }

        return [
            'path' => $tmpPath,
            'filename' => $filename,
            'size' => $size,
        ];
    }

    // ── private helpers ──────────────────────────────────────────────────────

    private function validateUrl(string $url): void
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw InvalidUploadUrlException::invalidUrl($url);
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw InvalidUploadUrlException::invalidScheme((string)$scheme);
        }

        // Basic SSRF guard: block private / loopback IPs supplied as literals.
        $host = (string)parse_url($url, PHP_URL_HOST);
        if ($host !== '' && filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $publicIp = filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
            if ($publicIp === false) {
                throw InvalidUploadUrlException::blockedHost($host);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function headRequest(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'HEAD',
                'timeout' => self::CONNECT_TIMEOUT_S,
                'follow_location' => 1,
                'max_redirects' => 5,
                'user_agent' => self::USER_AGENT,
                'ignore_errors' => true,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);

        $raw = @get_headers($url, true, $context);
        if ($raw === false) {
            return [];
        }

        $normalized = [];
        foreach ($raw as $key => $value) {
            // get_headers returns "HTTP/1.x 200 OK" as numeric key 0
            if (is_int($key)) {
                continue;
            }
            // When there are redirects, values may be arrays; take the last one.
            $normalized[strtolower($key)] = is_array($value) ? (string)end($value) : (string)$value;
        }

        return $normalized;
    }

    /**
     * @param array<string, string> $headHeaders
     */
    private function extractFilename(string $url, array $headHeaders): string
    {
        // Try Content-Disposition: attachment; filename="foo.mp4"
        if (isset($headHeaders['content-disposition'])) {
            if (preg_match(
                '/filename\*?=["\']?(?:UTF-8\'\')?([^"\';\s]+)/i',
                $headHeaders['content-disposition'],
                $m
            )) {
                $name = urldecode(trim($m[1], '"\''));
                if ($name !== '') {
                    return $name;
                }
            }
        }

        // Fall back to the URL path basename.
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        return urldecode(pathinfo($path, PATHINFO_BASENAME));
    }

    private function streamDownload(string $url, string $destPath, int $maxBytes): void
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => self::DOWNLOAD_TIMEOUT_S,
                'follow_location' => 1,
                'max_redirects' => 5,
                'user_agent' => self::USER_AGENT,
                'ignore_errors' => false,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);

        $src = @fopen($url, 'rb', false, $context);
        if ($src === false) {
            throw InvalidUploadUrlException::downloadFailed($url);
        }

        $dst = @fopen($destPath, 'wb');
        if ($dst === false) {
            fclose($src);
            throw InvalidUploadUrlException::downloadFailed($url);
        }

        $downloaded = 0;
        try {
            while (!feof($src)) {
                $chunk = fread($src, self::READ_CHUNK_BYTES);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $downloaded += strlen($chunk);
                if ($downloaded > $maxBytes) {
                    throw InvalidUploadUrlException::tooLarge($downloaded, $maxBytes);
                }
                fwrite($dst, $chunk);
            }
        } catch (\Throwable $e) {
            fclose($src);
            fclose($dst);
            @unlink($destPath);
            throw $e;
        }

        fclose($src);
        fclose($dst);
    }
}
