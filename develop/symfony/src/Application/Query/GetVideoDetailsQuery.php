<?php
declare(strict_types=1);

namespace App\Application\Query;

use App\Application\Exception\QueryException;
use App\Domain\Shared\ValueObject\Uuid;
use Throwable;

final readonly class GetVideoDetailsQuery
{
    public Uuid $uuid;

    public function __construct(string $uuid)
    {
        try {
            $this->uuid = Uuid::fromString($uuid);
        } catch (Throwable) {
            throw new QueryException('Invalid UUID');
        }
    }
}
