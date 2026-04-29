<?php
declare(strict_types=1);

namespace App\Application\Command\Video;

use App\Domain\Shared\ValueObject\Uuid;
use TusPhp\File;

final readonly class CreateVideo
{
    public function __construct(
        private ?File $file,
        private Uuid $userId,
        private ?string $url = null,
    ) {
    }

    public function file(): ?File
    {
        return $this->file;
    }

    public function userId(): Uuid
    {
        return $this->userId;
    }

    public function url(): ?string
    {
        return $this->url;
    }

    /**
     * Best-effort filename for logging / event payloads.
     * For Tus uploads returns the real file name; for URL uploads returns the URL basename.
     */
    public function filename(): string
    {
        if ($this->file !== null) {
            return $this->file->getName();
        }

        $path = parse_url($this->url ?? '', PHP_URL_PATH) ?? '';
        $basename = basename((string)$path);

        return $basename !== '' ? $basename : 'video.mp4';
    }
}
