<?php

namespace App\Domain\User\Repository;

use App\Domain\User\Entity\User;

interface UserRepositoryInterface {
    public function save(User $user): void;
    public function findById(int $id): ?User;
}
