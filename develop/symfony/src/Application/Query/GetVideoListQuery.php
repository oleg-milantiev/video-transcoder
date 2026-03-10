<?php

namespace App\Application\Query;

readonly class GetVideoListQuery {
    public function __construct(
        public int $page = 1,
        public int $limit = 10
    ) {}
}
