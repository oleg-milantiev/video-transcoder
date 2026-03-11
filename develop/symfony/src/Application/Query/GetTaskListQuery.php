<?php

namespace App\Application\Query;

final readonly class GetTaskListQuery
{
    use GetListQueryTrait;

    protected const int DEFAULT_LIMIT = 10;
    protected const int MAX_LIMIT = 9999;
    protected const int MAX_PAGE = 9999;

    public int $page;
    public int $limit;
}
