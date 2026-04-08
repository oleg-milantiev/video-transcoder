<?php
declare(strict_types=1);

namespace App\Application\Command\Message;

final readonly class TelegramMessage
{
    public function __construct(
        public int $chatId,
        public string $text,
        public bool $silent,
    ) {
    }
}
