<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Persistence\Doctrine\Mapper;

use App\Infrastructure\Persistence\Doctrine\User\TariffEntity;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Persistence\Doctrine\User\UserMapper;
use App\Domain\User\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

final class UserMapperTest extends TestCase
{
    private UserEntity $entity;

    protected function setUp(): void
    {
        $tariff = new TariffEntity();
        $tariff->id = SymfonyUuid::fromString('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa');
        $tariff->title = 'Free';
        $tariff->delay = 0;
        $tariff->instance = 1;
        $tariff->videoDuration = 600;
        $tariff->videoSize = 100.0;
        $tariff->maxWidth = 1920;
        $tariff->maxHeight = 1080;
        $tariff->storageGb = 5.0;
        $tariff->storageHour = 24;

        $this->entity = new UserEntity();
        $this->entity->id = SymfonyUuid::fromString('55555555-5555-4555-8555-555555555555');
        $this->entity->email = 'user@example.com';
        $this->entity->roles = ['ROLE_USER'];
        $this->entity->password = 'hashed-password';
        $this->entity->tariff = $tariff;
        $this->entity->createdAt = new DateTimeImmutable('2024-01-01');
        $this->entity->loginedAt = new DateTimeImmutable('2024-06-01');
        $this->entity->profile = [];
    }

    public function testToDomainMapsAllFields(): void
    {
        $user = UserMapper::toDomain($this->entity);

        self::assertInstanceOf(User::class, $user);
        self::assertSame('55555555-5555-4555-8555-555555555555', $user->id()->toRfc4122());
        self::assertSame('user@example.com', $user->email()->value());
        self::assertContains('ROLE_USER', $user->roles()->values());
        self::assertNotNull($user->tariff());
        self::assertSame('Free', $user->tariff()->title()->value());
        self::assertNotNull($user->loginedAt());
    }

    public function testToDomainWithNullTariff(): void
    {
        $this->entity->tariff = null;
        $user = UserMapper::toDomain($this->entity);

        self::assertNull($user->tariff());
    }

    public function testToDomainWithNullLoginedAt(): void
    {
        $this->entity->loginedAt = null;
        $user = UserMapper::toDomain($this->entity);

        self::assertNull($user->loginedAt());
    }

    public function testToDoctrineCreatesEntityWithCorrectFields(): void
    {
        $user = UserMapper::toDomain($this->entity);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getReference')->willReturn($this->entity->tariff);

        $entity = UserMapper::toDoctrine($user, $em);

        self::assertSame('55555555-5555-4555-8555-555555555555', $entity->id->toRfc4122());
        self::assertSame('user@example.com', $entity->email);
        self::assertContains('ROLE_USER', $entity->roles);
        self::assertSame('hashed-password', $entity->password);
        self::assertIsArray($entity->profile);
    }

    public function testToDoctrineWithNullId(): void
    {
        $this->entity->id = null;
        $user = UserMapper::toDomain($this->entity);

        $em = $this->createStub(EntityManagerInterface::class);
        $entity = UserMapper::toDoctrine($user, $em);

        self::assertNull($entity->id);
    }
}
