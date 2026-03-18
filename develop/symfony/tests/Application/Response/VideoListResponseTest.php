<?php

declare(strict_types=1);

namespace App\Tests\Application\Response;

use App\Application\Response\VideoListResponse;
use App\Tests\Domain\Entity\VideoFake;
use PHPUnit\Framework\TestCase;

class VideoListResponseTest extends TestCase
{
    public function testFromDomainMapsVideosAndPagination(): void
    {
        $videos = [new VideoFake(), new VideoFake()];
        $response = VideoListResponse::fromDomain($videos, total: 12, page: 2, limit: 5);

        $this->assertCount(2, $response->items);
        $this->assertSame(12, $response->total);
        $this->assertSame(2, $response->page);
        $this->assertSame(5, $response->limit);
        $this->assertSame(3, $response->totalPages);
        $this->assertSame($videos[0]->title()->value(), $response->items[0]->title);
    }

    public function testFromDomainHandlesEmptyList(): void
    {
        $response = VideoListResponse::fromDomain([], total: 0, page: 1, limit: 10);

        $this->assertSame([], $response->items);
        $this->assertSame(0, $response->total);
        $this->assertSame(0, $response->totalPages);
    }
}

