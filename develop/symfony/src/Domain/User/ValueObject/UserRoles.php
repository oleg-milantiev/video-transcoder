<?php
declare(strict_types=1);

namespace App\Domain\User\ValueObject;

final readonly class UserRoles
{
    /**
     * @var list<string>
     */
    private array $values;

    /**
     * @param list<string> $values
     */
    public function __construct(array $values)
    {
        if ($values === []) {
            throw new \DomainException('User must have at least one role.');
        }

        $normalized = [];

        foreach ($values as $value) {
            $role = strtoupper(trim($value));

            if ($role === '') {
                throw new \DomainException('User role cannot be empty.');
            }

            if (!preg_match('/^ROLE_[A-Z0-9_]+$/', $role)) {
                throw new \DomainException(sprintf('Invalid user role: %s.', $value));
            }

            $normalized[$role] = true;
        }

        $this->values = array_keys($normalized);
    }

    /**
     * @return list<string>
     */
    public function values(): array
    {
        return $this->values;
    }

    public function has(string $role): bool
    {
        return in_array(strtoupper(trim($role)), $this->values, true);
    }

    public function equals(self $other): bool
    {
        return $this->values === $other->values;
    }
}
