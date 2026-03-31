<?php
declare(strict_types=1);

namespace App\Application\Query;

use App\Application\Exception\QueryException;
use App\Domain\Shared\ValueObject\Uuid;
use Symfony\Component\HttpFoundation\Request;

trait GetListQueryTrait
{
    public function __construct(Request $request, Uuid $userId)
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', self::DEFAULT_LIMIT);

        if ($page < 1 || $page > self::MAX_PAGE) {
            throw new QueryException("Page must be between 1 and " . self::MAX_PAGE);
        }
        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new QueryException("Limit must be between 1 and " . self::MAX_LIMIT);
        }
        $this->page = $page;
        $this->limit = $limit;
        $this->userId = $userId;
    }
}
