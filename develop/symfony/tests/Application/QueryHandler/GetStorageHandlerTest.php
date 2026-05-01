<?php
declare(strict_types=1);

namespace App\Tests\Application\QueryHandler;

use App\Application\Query\GetStorageQuery;
use App\Application\QueryHandler\GetStorageHandler;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Tests\Domain\Entity\VideoFake;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;

final class GetStorageHandlerTest extends TestCase
{
    private function makeHandler(
        ?VideoRepositoryInterface $videoRepo = null,
        ?Security $security = null,
    ): GetStorageHandler {
        return new GetStorageHandler(
            $videoRepo ?? $this->createStub(VideoRepositoryInterface::class),
            $security ?? $this->createStub(Security::class),
        );
    }

    /** Невалидный UUID в первом сегменте ключа → 403. */
    public function testReturns403WhenKeyHasInvalidUuid(): void
    {
        $handler = $this->makeHandler();
        $response = $handler(new GetStorageQuery('not-a-uuid/preset.720.mp4'));
        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    /** Ключ без разделителя и невалидный UUID → 403. */
    public function testReturns403WhenKeyHasNoSlashAndInvalidUuid(): void
    {
        $handler = $this->makeHandler();
        $response = $handler(new GetStorageQuery('invalid'));
        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    /** Видео не найдено по UUID → 403 (не раскрываем наличие ресурса). */
    public function testReturns403WhenVideoNotFound(): void
    {
        $videoRepo = $this->createStub(VideoRepositoryInterface::class);
        $videoRepo->method('findById')->willReturn(null);
        $handler = $this->makeHandler(videoRepo: $videoRepo);

        $validUuid = '11111111-1111-4111-8111-111111111111';
        $response = $handler(new GetStorageQuery($validUuid . '/preset.720.mp4'));
        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    /** Видео найдено, но voter отклоняет → 403. */
    public function testReturns403WhenAccessDenied(): void
    {
        $video = VideoFake::create();
        $videoRepo = $this->createStub(VideoRepositoryInterface::class);
        $videoRepo->method('findById')->willReturn($video);

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(false);

        $handler = $this->makeHandler(videoRepo: $videoRepo, security: $security);
        $response = $handler(new GetStorageQuery($video->id()->toRfc4122() . '/preset.720.mp4'));
        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    /** Видео найдено, voter разрешает → 200. */
    public function testReturns200WhenAccessGranted(): void
    {
        $video = VideoFake::create();
        $videoRepo = $this->createStub(VideoRepositoryInterface::class);
        $videoRepo->method('findById')->willReturn($video);

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(true);

        $handler = $this->makeHandler(videoRepo: $videoRepo, security: $security);
        $response = $handler(new GetStorageQuery($video->id()->toRfc4122() . '/preset.720.mp4'));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    /** Ключ без разделителя, но UUID валидный — ищет видео по нему. */
    public function testReturns200WhenKeyIsJustUuidAndAccessGranted(): void
    {
        $video = VideoFake::create();
        $videoRepo = $this->createStub(VideoRepositoryInterface::class);
        $videoRepo->method('findById')->willReturn($video);

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(true);

        $handler = $this->makeHandler(videoRepo: $videoRepo, security: $security);
        $response = $handler(new GetStorageQuery($video->id()->toRfc4122()));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }
}
