<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller\Api;

use App\Application\Exception\QueryException;
use App\Application\Query\GetVideoDetailsQuery;
use App\Application\Query\GetVideoListQuery;
use App\Application\Query\StartTranscodeQuery;
use App\Application\QueryHandler\QueryBus;
use Symfony\Component\Uid\UuidV4;

final class VideoApiControllerTest extends ApiWebTestCase
{
    /**
     * @throws \JsonException
     */
    public function testListReturnsPaginatedPayload(): void
    {
        $client = $this->createBearerAuthenticatedClient();

        $items = [
            ['uuid' => '11111111-1111-4111-8111-111111111111', 'title' => 'Video A'],
            ['uuid' => '22222222-2222-4222-8222-222222222222', 'title' => 'Video B'],
        ];

        $listPayload = [
            'items' => $items,
            'total' => 17,
            'page' => 2,
            'limit' => 5,
            'totalPages' => 4,
        ];

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->with($this->callback(function (object $query): bool {
                return $query instanceof GetVideoListQuery
                    && $query->page === 2
                    && $query->limit === 5;
            }))
            ->willReturn((object) $listPayload);

        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('GET', '/api/video/?page=2&limit=5');

        self::assertResponseStatusCodeSame(200);
        self::assertSame($listPayload, $this->decodeJson($client->getResponse()->getContent()));
    }

    /**
     * @throws \JsonException
     */
    public function testListReturnsBadRequestOnQueryException(): void
    {
        $client = $this->createBearerAuthenticatedClient();

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->willThrowException(new QueryException('List failed'));
        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('GET', '/api/video/');

        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => 'List failed'], $this->decodeJson($client->getResponse()->getContent()));
    }

    /**
     * @throws \JsonException
     */
    public function testDetailsReturnsPayload(): void
    {
        $client = $this->createBearerAuthenticatedClient();

        $payload = [
            'id' => '11111111-1111-4111-8111-111111111111',
            'title' => 'Video A',
            'extension' => 'mp4',
            'status' => 'READY',
            'createdAt' => '2026-03-19 09:00',
            'updatedAt' => null,
            'userId' => '11111111-1111-4111-8111-111111111111',
            'meta' => ['duration' => 12],
            'poster' => null,
            'presetsWithTasks' => [],
        ];

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->with($this->callback(function (object $query): bool {
                return $query instanceof GetVideoDetailsQuery
                    && $query->uuid->toRfc4122() === '11111111-1111-4111-8111-111111111111';
            }))
            ->willReturn((object) $payload);

        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('GET', '/api/video/11111111-1111-4111-8111-111111111111');

        self::assertResponseStatusCodeSame(200);
        self::assertSame($payload, $this->decodeJson($client->getResponse()->getContent()));
    }

    /**
     * @throws \JsonException
     */
    public function testDetailsReturnsNotFoundOnQueryException(): void
    {
        $client = $this->createBearerAuthenticatedClient();

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->willThrowException(new QueryException('Video not found'));
        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('GET', '/api/video/11111111-1111-4111-8111-111111111111');

        self::assertResponseStatusCodeSame(404);
        self::assertSame(['error' => 'Video not found'], $this->decodeJson($client->getResponse()->getContent()));
    }

    /**
     * @throws \JsonException
     */
    public function testTranscodeReturnsTaskPayload(): void
    {
        $client = $this->createBearerAuthenticatedClient(userId: UuidV4::fromString('00000000-0000-4000-8000-000000000042'));

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->with($this->callback(function (object $query): bool {
                return $query instanceof StartTranscodeQuery
                    && $query->uuid->toRfc4122() === '11111111-1111-4111-8111-111111111111'
                    && $query->presetId->toRfc4122() === '77777777-7777-4777-8777-777777777777'
                    && $query->userId->toRfc4122() === '00000000-0000-4000-8000-000000000042';
            }))
            ->willReturn(['taskId' => '15151515-1515-4515-8515-151515151515', 'status' => 'PENDING']);

        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('POST', '/api/video/11111111-1111-4111-8111-111111111111/transcode/77777777-7777-4777-8777-777777777777');

        self::assertResponseStatusCodeSame(200);
        self::assertSame([
            'data' => [
                'task' => ['taskId' => '15151515-1515-4515-8515-151515151515', 'status' => 'PENDING'],
            ],
        ], $this->decodeJson($client->getResponse()->getContent()));
    }

    /**
     * @throws \JsonException
     */
    public function testTranscodeReturnsBadRequestForInvalidUuid(): void
    {
        $client = $this->createBearerAuthenticatedClient();

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->never())->method('query');
        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('POST', '/api/video/not-a-uuid/transcode/77777777-7777-4777-8777-777777777777');

        self::assertResponseStatusCodeSame(400);
        self::assertSame([
            'error' => [
                'code' => 'INVALID_UUID',
                'message' => 'Invalid UUID',
                'details' => [],
            ],
        ], $this->decodeJson($client->getResponse()->getContent()));
    }

    /**
     * @throws \JsonException
     */
    public function testTranscodeReturnsForbiddenOnDomainException(): void
    {
        $client = $this->createBearerAuthenticatedClient();

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->willThrowException(new \DomainException('Access denied'));
        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('POST', '/api/video/11111111-1111-4111-8111-111111111111/transcode/77777777-7777-4777-8777-777777777777');

        self::assertResponseStatusCodeSame(403);
        self::assertSame([
            'error' => [
                'code' => 'ACCESS_DENIED',
                'message' => 'Access denied',
                'details' => [],
            ],
        ], $this->decodeJson($client->getResponse()->getContent()));
    }

    /**
     * @throws \JsonException
     */
    public function testTranscodeReturnsServerErrorOnUnexpectedException(): void
    {
        $client = $this->createBearerAuthenticatedClient();

        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects($this->once())
            ->method('query')
            ->willThrowException(new \RuntimeException('boom'));
        $this->replaceService(QueryBus::class, $queryBus);

        $client->request('POST', '/api/video/11111111-1111-4111-8111-111111111111/transcode/77777777-7777-4777-8777-777777777777');

        self::assertResponseStatusCodeSame(500);
        self::assertSame([
            'error' => [
                'code' => 'INTERNAL_ERROR',
                'message' => 'Failed to start transcode',
                'details' => [],
            ],
        ], $this->decodeJson($client->getResponse()->getContent()));
    }

    /**
     * @return array<mixed>
     * @throws \JsonException
     */
    private function decodeJson(?string $content): array
    {
        self::assertNotNull($content);

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}


