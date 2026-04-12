<?php

declare(strict_types=1);

namespace App\Tests\Application\Query;

use App\Application\Exception\InvalidUuidException;
use App\Application\Query\PatchVideoQuery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class PatchVideoQueryTest extends TestCase
{
    public function testCreatesQueryWithValidData(): void
    {
        $videoId = '11111111-1111-4111-8111-111111111111';
        $userId = '22222222-2222-4222-8222-222222222222';
        $request = Request::create(
            '/api/video/' . $videoId,
            'PATCH',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['title' => 'New Title'])
        );

        $query = new PatchVideoQuery($videoId, $request, $userId);

        $this->assertSame($videoId, $query->videoId->toRfc4122());
        $this->assertSame($userId, $query->requestedByUserId->toRfc4122());
        $this->assertSame('New Title', $query->title);
    }

    public function testThrowsWhenTitleIsMissing(): void
    {
        $videoId = '11111111-1111-4111-8111-111111111111';
        $userId = '22222222-2222-4222-8222-222222222222';
        $request = Request::create(
            '/api/video/' . $videoId,
            'PATCH',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['other' => 'data'])
        );

        $this->expectException(InvalidUuidException::class);
        new PatchVideoQuery($videoId, $request, $userId);
    }

    public function testThrowsWhenTitleIsNull(): void
    {
        $videoId = '11111111-1111-4111-8111-111111111111';
        $userId = '22222222-2222-4222-8222-222222222222';
        $request = Request::create(
            '/api/video/' . $videoId,
            'PATCH',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([])
        );

        $this->expectException(InvalidUuidException::class);
        new PatchVideoQuery($videoId, $request, $userId);
    }

    public function testThrowsWhenVideoIdIsInvalid(): void
    {
        $request = Request::create(
            '/api/video/not-a-uuid',
            'PATCH',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['title' => 'New Title'])
        );

        $this->expectException(InvalidUuidException::class);
        new PatchVideoQuery('not-a-uuid', $request, '22222222-2222-4222-8222-222222222222');
    }

    public function testThrowsWhenUserIdIsInvalid(): void
    {
        $videoId = '11111111-1111-4111-8111-111111111111';
        $request = Request::create(
            '/api/video/' . $videoId,
            'PATCH',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['title' => 'New Title'])
        );

        $this->expectException(InvalidUuidException::class);
        new PatchVideoQuery($videoId, $request, 'not-a-uuid');
    }
}
