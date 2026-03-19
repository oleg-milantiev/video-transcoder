<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller\Api;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final readonly class InMemoryTestUserProvider implements UserProviderInterface
{
    public function __construct(private UserInterface $user)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        if ($this->user->getUserIdentifier() !== $identifier) {
            throw new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
        }

        return $this->user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$this->supportsClass($user::class)) {
            throw new UnsupportedUserException(sprintf('Unsupported user class: %s', $user::class));
        }

        return $this->user;
    }

    public function supportsClass(string $class): bool
    {
        return is_a($class, $this->user::class, true);
    }
}

