<?php

declare(strict_types=1);

namespace App\Tests\Application\DTO;

use App\Application\DTO\PaginatedResult;
use PHPUnit\Framework\TestCase;

class PaginatedResultTest extends TestCase
{
    public function testStoresItemsAndTotal(): void
    {
        $items = ['foo', 'bar'];
        $result = new PaginatedResult($items, 2);

        $this->assertSame($items, $result->items);
        $this->assertSame(2, $result->total);
    }
}

