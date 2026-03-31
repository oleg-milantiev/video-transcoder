<?php
declare(strict_types=1);

namespace App\Application\Service\Mercure;

use App\Application\DTO\MercureMessageDTO;

interface MercurePublisherInterface
{
    public function publish(MercureMessageDTO $message): void;
}
