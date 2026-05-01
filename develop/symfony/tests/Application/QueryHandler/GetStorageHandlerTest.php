<?php
declare(strict_types=1);

namespace App\Tests\Application\QueryHandler;

use App\Application\Query\GetStorageQuery;
use App\Application\QueryHandler\GetStorageHandler;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Tests\Domain\Entity\VideoFake;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

final class GetStorageHandlerTest extends TestCase
{
    private function makeHandler(
        ?VideoRepositoryInterface $videoRepo = null,
        ?Security $security = null,
        ?CacheItemPoolInterface $cache = null,
    ): GetStorageHandler {
        return new GetStorageHandler(
            $videoRepo ?? $this->createStub(VideoRepositoryInterface::class),
            $security ?? $this->createStub(Security::class),
            $cache ?? $this->createStub(CacheItemPoolInterface::class),
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

        $user = new UserEntity();
        $user->id = SymfonyUuid::v4();
        $security->method('getUser')->willReturn($user);

        $cache = $this->createStub(CacheItemPoolInterface::class);
        $cacheItem = $this->createStub(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);
        $cacheItem->method('expiresAfter')->willReturn($cacheItem);
        $cache->method('getItem')->willReturn($cacheItem);

        $handler = $this->makeHandler(videoRepo: $videoRepo, security: $security, cache: $cache);
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

        $user = new UserEntity();
        $user->id = SymfonyUuid::v4();
        $security->method('getUser')->willReturn($user);

        $cache = $this->createStub(CacheItemPoolInterface::class);
        $cacheItem = $this->createStub(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);
        $cacheItem->method('expiresAfter')->willReturn($cacheItem);
        $cache->method('getItem')->willReturn($cacheItem);

        $handler = $this->makeHandler(videoRepo: $videoRepo, security: $security, cache: $cache);
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

        $user = new UserEntity();
        $user->id = SymfonyUuid::v4();
        $security->method('getUser')->willReturn($user);

        $cache = $this->createStub(CacheItemPoolInterface::class);
        $cacheItem = $this->createStub(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);
        $cacheItem->method('expiresAfter')->willReturn($cacheItem);
        $cache->method('getItem')->willReturn($cacheItem);

        $handler = $this->makeHandler(videoRepo: $videoRepo, security: $security, cache: $cache);
        $response = $handler(new GetStorageQuery($video->id()->toRfc4122()));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    /** Неаутентифицированный пользователь получает 403. */
    public function testReturns403WhenUserNotAuthenticated(): void
    {
        $videoUuid = '11111111-1111-4111-8111-111111111111';
        $key = $videoUuid . '/preset.720.mp4';

        $videoRepo = $this->createStub(VideoRepositoryInterface::class);

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $handler = $this->makeHandler(videoRepo: $videoRepo, security: $security);
        $response = $handler(new GetStorageQuery($key));
        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    /** Кеш попадание для разрешенного доступа — videoRepo не вызывается. */
    public function testCacheHitForAllowedAccessSkipsRepository(): void
    {
        $video = VideoFake::create();
        $videoUuid = $video->id()->toRfc4122();
        $key = $videoUuid . '/preset.720.mp4';

        $cacheItem = $this->createStub(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);
        $cacheItem->method('get')->willReturn(true);

        $cache = $this->createStub(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($cacheItem);

        // videoRepo должен НЕ быть вызван благодаря кешу
        $videoRepo = $this->createMock(VideoRepositoryInterface::class);
        $videoRepo->expects($this->never())->method('findById');

        $security = $this->createStub(Security::class);

        $user = new UserEntity();
        $user->id = SymfonyUuid::v4();
        $security->method('getUser')->willReturn($user);

        $handler = $this->makeHandler(videoRepo: $videoRepo, security: $security, cache: $cache);
        $response = $handler(new GetStorageQuery($key));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    /** Кеш попадание для запрещенного доступа — возвращает 403 без проверок. */
    public function testCacheHitForDeniedAccessReturns403Immediately(): void
    {
        $video = VideoFake::create();
        $videoUuid = $video->id()->toRfc4122();
        $key = $videoUuid . '/preset.720.mp4';

        $cacheItem = $this->createStub(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);
        $cacheItem->method('get')->willReturn(false);

        $cache = $this->createStub(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($cacheItem);

        // videoRepo должен НЕ быть вызван благодаря кешу
        $videoRepo = $this->createMock(VideoRepositoryInterface::class);
        $videoRepo->expects($this->never())->method('findById');

        $security = $this->createStub(Security::class);

        $user = new UserEntity();
        $user->id = SymfonyUuid::v4();
        $security->method('getUser')->willReturn($user);

        $handler = $this->makeHandler(videoRepo: $videoRepo, security: $security, cache: $cache);
        $response = $handler(new GetStorageQuery($key));
        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    /** Кеш промах для разрешенного доступа — проверяет, сохраняет и возвращает 200. */
    public function testCacheMissForAllowedAccessSavesPositiveCache(): void
    {
        $video = VideoFake::create();
        $videoUuid = $video->id()->toRfc4122();
        $key = $videoUuid . '/preset.720.mp4';

        $videoRepo = $this->createStub(VideoRepositoryInterface::class);
        $videoRepo->method('findById')->willReturn($video);

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(true);

        $user = new UserEntity();
        $user->id = SymfonyUuid::v4();
        $security->method('getUser')->willReturn($user);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);
        $cacheItem->expects($this->once())->method('expiresAfter')->willReturn($cacheItem);
        $cacheItem->expects($this->once())->method('set')->with(true)->willReturn($cacheItem);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($cacheItem);
        $cache->expects($this->once())->method('save')->with($cacheItem);

        $handler = $this->makeHandler(videoRepo: $videoRepo, security: $security, cache: $cache);
        $response = $handler(new GetStorageQuery($key));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    /** Кеш промах для запрещенного доступа — проверяет, сохраняет и возвращает 403. */
    public function testCacheMissForDeniedAccessSavesNegativeCache(): void
    {
        $video = VideoFake::create();
        $videoUuid = $video->id()->toRfc4122();
        $key = $videoUuid . '/preset.720.mp4';

        $videoRepo = $this->createStub(VideoRepositoryInterface::class);
        $videoRepo->method('findById')->willReturn($video);

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(false);

        $user = new UserEntity();
        $user->id = SymfonyUuid::v4();
        $security->method('getUser')->willReturn($user);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);
        $cacheItem->expects($this->once())->method('expiresAfter')->willReturn($cacheItem);
        $cacheItem->expects($this->once())->method('set')->with(false)->willReturn($cacheItem);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($cacheItem);
        $cache->expects($this->once())->method('save')->with($cacheItem);

        $handler = $this->makeHandler(videoRepo: $videoRepo, security: $security, cache: $cache);
        $response = $handler(new GetStorageQuery($key));
        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    /** Кеш промах для ненайденного видео — сохраняет отрицательный кеш и возвращает 403. */
    public function testCacheMissWhenVideoNotFoundSavesNegativeCache(): void
    {
        $videoUuid = '11111111-1111-4111-8111-111111111111';
        $key = $videoUuid . '/preset.720.mp4';

        $videoRepo = $this->createStub(VideoRepositoryInterface::class);
        $videoRepo->method('findById')->willReturn(null);

        $security = $this->createStub(Security::class);

        $user = new UserEntity();
        $user->id = SymfonyUuid::v4();
        $security->method('getUser')->willReturn($user);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);
        $cacheItem->expects($this->once())->method('expiresAfter')->willReturn($cacheItem);
        $cacheItem->expects($this->once())->method('set')->with(false)->willReturn($cacheItem);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($cacheItem);
        $cache->expects($this->once())->method('save')->with($cacheItem);

        $handler = $this->makeHandler(videoRepo: $videoRepo, security: $security, cache: $cache);
        $response = $handler(new GetStorageQuery($key));
        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    /** Кеш ключ включает UUID видео и UUID пользователя. */
    public function testCacheKeyIncludesVideoAndUserUuid(): void
    {
        $video = VideoFake::create();
        $videoUuid = $video->id()->toRfc4122();
        $key = $videoUuid . '/preset.720.mp4';

        $videoRepo = $this->createStub(VideoRepositoryInterface::class);
        $videoRepo->method('findById')->willReturn($video);

        // Use createMock and set expectations
        $security = $this->createMock(Security::class);
        $security->expects($this->once())->method('isGranted')->willReturn(true);

        $user = new UserEntity();
        $user->id = SymfonyUuid::v4();
        $security->expects($this->once())->method('getUser')->willReturn($user);

        // Use createMock and set expectation for cache key
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cacheItem = $this->createStub(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);
        $cacheItem->method('set')->willReturn($cacheItem);
        $cacheItem->method('expiresAfter')->willReturn($cacheItem);

        $expectedCacheKey = 'storage_access_' . $videoUuid . '_' . $user->id->toRfc4122();
        $cache->expects($this->once())
            ->method('getItem')
            ->with($expectedCacheKey)
            ->willReturn($cacheItem);
        $cache->method('save')->willReturn(true);

        $handler = $this->makeHandler(videoRepo: $videoRepo, security: $security, cache: $cache);
        $response = $handler(new GetStorageQuery($key));
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }
}
