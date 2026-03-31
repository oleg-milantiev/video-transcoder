<?php

declare(strict_types=1);

namespace App\Tests\Application\Query;

use App\Application\Exception\InvalidUuidException;
use App\Application\Query\DeleteVideoQuery;
use App\Application\Query\StartTranscodeQuery;
use App\Application\Query\PatchVideoQuery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class QueryTest extends TestCase
{
    public function testDeleteVideoQueryCreatesInstanceWithValidUuids(): void
    {
        $videoId = '11111111-1111-4111-8111-111111111111';
        $userId = '22222222-2222-4222-8222-222222222222';

        $query = new DeleteVideoQuery($videoId, $userId);

        $this->assertSame($videoId, $query->videoId->toRfc4122());
        $this->assertSame($userId, $query->requestedByUserId->toRfc4122());
    }

    public function testDeleteVideoQueryThrowsWhenInvalidVideoId(): void
    {
        $this->expectException(InvalidUuidException::class);
        new DeleteVideoQuery('invalid-uuid', '22222222-2222-4222-8222-222222222222');
    }

    public function testDeleteVideoQueryThrowsWhenInvalidUserId(): void
    {
        $this->expectException(InvalidUuidException::class);
        new DeleteVideoQuery('11111111-1111-4111-8111-111111111111', 'invalid-uuid');
    }

    public function testStartTranscodeQueryCreatesInstanceWithValidUuids(): void
    {
        $videoId = '11111111-1111-4111-8111-111111111111';
        $presetId = '33333333-3333-4333-8333-333333333333';
        $userId = '44444444-4444-4444-8444-444444444444';

        $query = new StartTranscodeQuery($videoId, $presetId, $userId);

        $this->assertSame($videoId, $query->uuid->toRfc4122());
        $this->assertSame($presetId, $query->presetId->toRfc4122());
        $this->assertSame($userId, $query->userId->toRfc4122());
    }

    public function testStartTranscodeQueryThrowsWhenInvalidVideoId(): void
    {
        $this->expectException(InvalidUuidException::class);
        new StartTranscodeQuery('invalid', '33333333-3333-4333-8333-333333333333', '44444444-4444-4444-8444-444444444444');
    }

    public function testStartTranscodeQueryThrowsWhenInvalidPresetId(): void
    {
        $this->expectException(InvalidUuidException::class);
        new StartTranscodeQuery('11111111-1111-4111-8111-111111111111', 'invalid', '44444444-4444-4444-8444-444444444444');
    }

    public function testStartTranscodeQueryThrowsWhenInvalidUserId(): void
    {
        $this->expectException(InvalidUuidException::class);
        new StartTranscodeQuery('11111111-1111-4111-8111-111111111111', '33333333-3333-4333-8333-333333333333', 'invalid');
    }

    public function testPatchVideoQueryCreatesInstanceWithValidData(): void
    {
        $videoId = '11111111-1111-4111-8111-111111111111';
        $userId = '22222222-2222-4222-8222-222222222222';

        $query = new PatchVideoQuery($videoId, $this->getRequestWithTitle('New Title'), $userId);

        $this->assertSame($videoId, $query->videoId->toRfc4122());
        $this->assertSame($userId, $query->requestedByUserId->toRfc4122());
        $this->assertSame('New Title', $query->title);
    }

    public function testPatchVideoQueryThrowsWhenInvalidVideoId(): void
    {
        $this->expectException(InvalidUuidException::class);
        new PatchVideoQuery('invalid', $this->getRequestWithTitle('Title'), '22222222-2222-4222-8222-222222222222');
    }

    public function testPatchVideoQueryThrowsWhenInvalidUserId(): void
    {
        $this->expectException(InvalidUuidException::class);
        new PatchVideoQuery('11111111-1111-4111-8111-111111111111', $this->getRequestWithTitle('Title'), 'invalid');
    }

    private function getRequestWithTitle(string $title): Request
    {
        $payload = json_encode(['title' => $title]);

        $request = $this->createStub(Request::class);
        $request->method('getContent')
            ->willReturn($payload);

        return $request;
    }
}
