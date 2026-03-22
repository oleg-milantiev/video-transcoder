<?php

declare(strict_types=1);

namespace App\Application\Command\Mercure;

use App\Application\DTO\MercureMessageDTO;

final readonly class PublishMercureMessage
{
    public function __construct(public MercureMessageDTO $message)
    {
    }
}

