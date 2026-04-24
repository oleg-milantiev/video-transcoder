<?php
declare(strict_types=1);

namespace App\Application\Query;

use App\Domain\Shared\ValueObject\Uuid;

final readonly class GetProfileQuery
{
    public function __construct(
        public Uuid $userId,
    ) {
    }
}
