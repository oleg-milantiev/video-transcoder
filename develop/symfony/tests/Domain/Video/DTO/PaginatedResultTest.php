<?php

declare(strict_types=1);

namespace App\Tests\Domain\Video\DTO;

use App\Domain\Video\DTO\PaginatedResult;
use PHPUnit\Framework\TestCase;

final class PaginatedResultTest extends TestCase
{
    public function testStoresItemsAndTotal(): void
    {
        $items = ['foo', 'bar'];
        $result = new PaginatedResult($items, 2);

        $this->assertSame($items, $result->items);
        $this->assertSame(2, $result->total);
    }
}
