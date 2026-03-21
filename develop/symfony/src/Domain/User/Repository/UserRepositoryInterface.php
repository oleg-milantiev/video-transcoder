<?php

namespace App\Domain\User\Repository;

use App\Domain\User\Entity\User;
use Symfony\Component\Uid\UuidV4 as Uuid;

interface UserRepositoryInterface {
    public function save(User $user): void;
    public function findById(Uuid $id): ?User;
    public function countAdmins(?Uuid $excludeId = null): int;
    public function log(Uuid $id, string $level, string $text): void;
}
