<?php
declare(strict_types=1);

namespace App\Application\DTO;

final readonly class TelegramMessageDTO
{
    public function __construct(
        public int $chatId,
        public string $text,
        public bool $silent,
    ) {}
}
