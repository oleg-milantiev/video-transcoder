<?php

namespace App\Infrastructure\Persistence\Doctrine\User;

use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\UuidV4 as Uuid;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<UserEntity>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface, UserRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserEntity::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->save($user);
    }

    public function save(User $user): void
    {
        $this->getEntityManager()->persist(UserMapper::toDoctrine($user));
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?User
    {
        $entity = $this->find($id);

        return $entity ? UserMapper::toDomain($entity) : null;
    }

    /**
     * @throws Exception
     */
    public function countAdmins(?Uuid $excludeId = null): int
    {
        $sql = 'SELECT count(id) FROM "user" WHERE roles::jsonb @> :role';

        if ($excludeId !== null) {
            $sql .= ' AND id != :id';
        }

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->bindValue('role', json_encode(['ROLE_ADMIN']));

        if ($excludeId !== null) {
            $stmt->bindValue('id', $excludeId->toRfc4122());
        }

        $result = $stmt->executeQuery();

        return (int) $result->fetchOne();
    }
}
