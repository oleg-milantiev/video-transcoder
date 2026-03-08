<?php

namespace App\Infrastructure\Validator\Constraints;

use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Persistence\Doctrine\User\UserRepository;
use Doctrine\DBAL\Exception;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class AtLeastOneAdminValidator extends ConstraintValidator
{
    public function __construct(
        private readonly UserRepository $userRepository
    ) {
    }

    /**
     * @throws Exception
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof AtLeastOneAdmin) {
            throw new UnexpectedTypeException($constraint, AtLeastOneAdmin::class);
        }

        // $value are the roles of the user being edited
        if (!is_array($value)) {
            return;
        }

        // If this user already has ROLE_ADMIN, we're okay.
        if (in_array('ROLE_ADMIN', $value, true)) {
            return;
        }

        /** @var UserEntity $user */
        $user = $this->context->getObject();

        // If this is a new user without ROLE_ADMIN, we just need to make sure someone else is an admin.
        // But the constraint is about NOT removing the LAST admin.
        // If we're editing an existing user and removing their admin role:
        if ($user->id !== null) {
            if ($this->userRepository->countAdmins($user->id) === 0) {
                $this->context->buildViolation($constraint->message)
                    ->addViolation();
            }
        }
    }
}
