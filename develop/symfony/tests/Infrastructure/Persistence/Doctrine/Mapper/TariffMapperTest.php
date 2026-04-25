<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Persistence\Doctrine\Mapper;

use App\Infrastructure\Persistence\Doctrine\User\TariffMapper;
use App\Infrastructure\Persistence\Doctrine\User\TariffEntity;
use App\Domain\User\Entity\Tariff;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

final class TariffMapperTest extends TestCase
{
    private TariffEntity $entity;

    protected function setUp(): void
    {
        $this->entity = new TariffEntity();
        $this->entity->id = SymfonyUuid::fromString('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa');
        $this->entity->title = 'Pro';
        $this->entity->delay = 0;
        $this->entity->instance = 3;
        $this->entity->videoDuration = 7200;
        $this->entity->videoSize = 500.0;
        $this->entity->maxWidth = 3840;
        $this->entity->maxHeight = 2160;
        $this->entity->storageGb = 50.0;
        $this->entity->storageHour = 48;
    }

    public function testToDomainMapsAllFields(): void
    {
        $tariff = TariffMapper::toDomain($this->entity);

        self::assertInstanceOf(Tariff::class, $tariff);
        self::assertSame('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', $tariff->id()->toRfc4122());
        self::assertSame('Pro', $tariff->title()->value());
        self::assertSame(0, $tariff->delay()->value());
        self::assertSame(3, $tariff->instance()->value());
        self::assertSame(7200, $tariff->videoDuration()->value());
        self::assertSame(500.0, $tariff->videoSize()->value());
        self::assertSame(3840, $tariff->maxWidth()->value());
        self::assertSame(2160, $tariff->maxHeight()->value());
        self::assertSame(50.0, $tariff->storageGb()->value());
        self::assertSame(48, $tariff->storageHour()->value());
    }

    public function testToDomainWithNullId(): void
    {
        $this->entity->id = null;
        $tariff = TariffMapper::toDomain($this->entity);

        self::assertNull($tariff->id());
    }
}
