<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\Video\Entity\Video;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Persistence\Doctrine\Video\VideoEntity;
use App\Infrastructure\Persistence\Doctrine\Video\VideoMapper;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

final class VideoMapperTest extends TestCase
{
    private UserEntity $userEntity;
    private VideoEntity $videoEntity;

    protected function setUp(): void
    {
        $this->userEntity = new UserEntity();
        $this->userEntity->id = SymfonyUuid::fromString('11111111-1111-4111-8111-111111111111');

        $this->videoEntity = new VideoEntity();
        $this->videoEntity->id = SymfonyUuid::fromString('22222222-2222-4222-8222-222222222222');
        $this->videoEntity->title = 'Test Video';
        $this->videoEntity->extension = 'mp4';
        $this->videoEntity->user = $this->userEntity;
        $this->videoEntity->meta = ['size' => 1024];
        $this->videoEntity->createdAt = new DateTimeImmutable('2024-01-01 10:00:00');
        $this->videoEntity->updatedAt = new DateTimeImmutable('2024-01-02 12:00:00');
        $this->videoEntity->deleted = false;
    }

    public function testToDomainMapsAllFields(): void
    {
        $video = VideoMapper::toDomain($this->videoEntity);

        self::assertInstanceOf(Video::class, $video);
        self::assertSame('22222222-2222-4222-8222-222222222222', $video->id()->toRfc4122());
        self::assertSame('Test Video', $video->title()->value());
        self::assertSame('mp4', $video->extension()->value());
        self::assertSame('11111111-1111-4111-8111-111111111111', $video->userId()->toRfc4122());
        self::assertSame(['size' => 1024], $video->meta());
        self::assertFalse($video->isDeleted());
    }

    public function testToDomainWithDeletedFlag(): void
    {
        $this->videoEntity->deleted = true;
        $video = VideoMapper::toDomain($this->videoEntity);

        self::assertTrue($video->isDeleted());
    }

    public function testToDoctrineCreatesEntityWithCorrectFields(): void
    {
        $video = VideoMapper::toDomain($this->videoEntity);
        $entity = VideoMapper::toDoctrine($video, $this->userEntity);

        self::assertInstanceOf(VideoEntity::class, $entity);
        self::assertSame('22222222-2222-4222-8222-222222222222', $entity->id->toRfc4122());
        self::assertSame('Test Video', $entity->title);
        self::assertSame('mp4', $entity->extension);
        self::assertSame($this->userEntity, $entity->user);
        self::assertSame(['size' => 1024], $entity->meta);
        self::assertFalse($entity->deleted);
    }

    public function testHydrateUpdatesExistingEntity(): void
    {
        $video = VideoMapper::toDomain($this->videoEntity);
        $target = new VideoEntity();
        VideoMapper::hydrate($target, $video, $this->userEntity);

        self::assertSame('Test Video', $target->title);
        self::assertSame('mp4', $target->extension);
        self::assertSame($this->userEntity, $target->user);
    }
}
