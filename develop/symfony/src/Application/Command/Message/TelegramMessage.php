<?php
declare(strict_types=1);

namespace App\Application\Command\Message;

use App\Application\DTO\TelegramMessageDTO;

final readonly class TelegramMessage
{
    public function __construct(public TelegramMessageDTO $message)
    {
    }
}
