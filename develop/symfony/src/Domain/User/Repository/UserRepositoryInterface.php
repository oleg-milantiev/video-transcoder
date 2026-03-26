<?php

namespace App\Domain\User\Repository;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\User\Entity\User;

interface UserRepositoryInterface {
    public function save(User $user): void;
    public function findById(Uuid $id): ?User;
    public function countAdmins(?Uuid $excludeId = null): int;
}
