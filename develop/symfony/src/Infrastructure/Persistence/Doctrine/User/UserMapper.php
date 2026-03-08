<?php

namespace App\Infrastructure\Persistence\Doctrine\User;

use App\Domain\User\Entity\User;

class UserMapper
{
    public static function toDomain(UserEntity $entity): User
    {
        return new User(
            email: $entity->email,
            roles: $entity->roles,
            password: $entity->password,
            tarif: $entity->tarif ? TarifMapper::toDomain($entity->tarif) : null,
            id: $entity->id,
        );
    }

    public static function toDoctrine(User $user): UserEntity
    {
        $entity = new UserEntity();
        $entity->id = $user->id();
        $entity->email = $user->email();
        $entity->roles = $user->roles();
        $entity->password = $user->password();
        $entity->tarif = $user->tarif() ? TarifMapper::toDoctrine($user->tarif()) : null;

        return $entity;
    }
}
