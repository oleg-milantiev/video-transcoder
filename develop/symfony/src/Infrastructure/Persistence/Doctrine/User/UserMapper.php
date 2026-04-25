<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\User;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\PasswordHash;
use App\Domain\User\ValueObject\UserCreatedAt;
use App\Domain\User\ValueObject\UserEmail;
use App\Domain\User\ValueObject\UserLoginedAt;
use App\Domain\User\ValueObject\UserProfile;
use App\Domain\User\ValueObject\UserRoles;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

class UserMapper
{
    public static function toDomain(UserEntity $entity): User
    {
        return new User(
            email: new UserEmail($entity->email ?? ''),
            roles: new UserRoles($entity->roles),
            password: $entity->password !== null ? new PasswordHash($entity->password) : null,
            tariff: $entity->tariff ? TariffMapper::toDomain($entity->tariff) : null,
            id: $entity->id ? Uuid::fromString($entity->id->toRfc4122()) : null,
            createdAt: new UserCreatedAt($entity->createdAt),
            loginedAt: $entity->loginedAt !== null ? new UserLoginedAt($entity->loginedAt) : null,
            profile: UserProfile::fromArray($entity->profile),
        );
    }

    public static function toDoctrine(User $user, EntityManagerInterface $em): UserEntity
    {
        $entity = new UserEntity();
        if ($user->id() !== null) {
            $entity->id = SymfonyUuid::fromString($user->id()->toRfc4122());
        }
        $entity->email = $user->email()->value();
        $entity->roles = $user->roles()->values();
        $entity->password = $user->password();
        $entity->tariff = $user->tariff()?->id() ? $em->getReference(TariffEntity::class, $user->tariff()->id()) : null;
        $entity->createdAt = $user->createdAt()->value();
        $entity->loginedAt = $user->loginedAt()?->value();
        $entity->profile = $user->profile()->toArray();

        return $entity;
    }
}
