<?php

namespace App\Application\Query;

use App\Application\Exception\QueryException;
use Symfony\Component\HttpFoundation\Request;

readonly class GetVideoListQuery {
    private const int DEFAULT_LIMIT = 10;
    private const int MAX_LIMIT = 9999;
    private const int MAX_PAGE = 9999;

    public int $page;
    public int $limit;

    public function __construct(Request $request)
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
    }
}
