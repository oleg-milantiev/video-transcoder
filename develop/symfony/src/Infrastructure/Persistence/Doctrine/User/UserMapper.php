<?php

namespace App\Infrastructure\Persistence\Doctrine\User;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\User\Entity\User;
use Symfony\Component\Uid\UuidV4 AS SymfonyUuid;

class UserMapper
{
    public static function toDomain(UserEntity $entity): User
    {
        return new User(
            email: $entity->email,
            roles: $entity->roles,
            password: $entity->password,
            tariff: $entity->tariff ? TariffMapper::toDomain($entity->tariff) : null,
            id: $entity->id ? Uuid::fromString($entity->id->toRfc4122()) : null,
        );
    }

    public static function toDoctrine(User $user): UserEntity
    {
        $entity = new UserEntity();
        if ($user->id() !== null) {
            $entity->id = SymfonyUuid::fromString($user->id()->toRfc4122());
        }
        $entity->email = $user->email();
        $entity->roles = $user->roles();
        $entity->password = $user->password();
        $entity->tariff = $user->tariff() ? TariffMapper::toDoctrine($user->tariff()) : null;

        return $entity;
    }
}
