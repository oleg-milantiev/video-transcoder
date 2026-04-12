<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Validator\Constraints;

use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Persistence\Doctrine\User\UserRepository;
use App\Presentation\Validator\Constraints\AtLeastOneAdmin;
use App\Presentation\Validator\Constraints\AtLeastOneAdminValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

final class AtLeastOneAdminValidatorTest extends TestCase
{
    public function testThrowsForWrongConstraintType(): void
    {
        $validator = new AtLeastOneAdminValidator($this->createStub(UserRepository::class));

        $this->expectException(UnexpectedTypeException::class);
        $validator->validate(['ROLE_USER'], $this->createStub(Constraint::class));
    }

    public function testSkipsValidationWhenValueIsNotArray(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $validator = new AtLeastOneAdminValidator($this->createStub(UserRepository::class));
        $validator->initialize($context);

        $validator->validate('not-an-array', new AtLeastOneAdmin());
    }

    public function testSkipsValidationWhenUserKeepsAdminRole(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $validator = new AtLeastOneAdminValidator($this->createStub(UserRepository::class));
        $validator->initialize($context);

        $validator->validate(['ROLE_ADMIN', 'ROLE_USER'], new AtLeastOneAdmin());
    }

    public function testSkipsViolationWhenUserHasNullId(): void
    {
        $user = new UserEntity();
        $user->id = null;

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->method('getObject')->willReturn($user);
        $context->expects($this->never())->method('buildViolation');

        $validator = new AtLeastOneAdminValidator($this->createStub(UserRepository::class));
        $validator->initialize($context);

        $validator->validate(['ROLE_USER'], new AtLeastOneAdmin());
    }

    public function testNoViolationWhenOtherAdminsExist(): void
    {
        $user = new UserEntity();
        $user->id = SymfonyUuid::fromString('11111111-1111-4111-8111-111111111111');

        $repository = $this->createStub(UserRepository::class);
        $repository->method('countAdmins')->willReturn(1);

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->once())->method('getObject')->willReturn($user);
        $context->expects($this->never())->method('buildViolation');

        $validator = new AtLeastOneAdminValidator($repository);
        $validator->initialize($context);

        $validator->validate(['ROLE_USER'], new AtLeastOneAdmin());
    }

    public function testAddsViolationWhenLastAdminIsRemoved(): void
    {
        $user = new UserEntity();
        $user->id = SymfonyUuid::fromString('22222222-2222-4222-8222-222222222222');

        $repository = $this->createStub(UserRepository::class);
        $repository->method('countAdmins')->willReturn(0);

        $violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $violationBuilder->expects($this->once())->method('addViolation');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->once())->method('getObject')->willReturn($user);
        $context->expects($this->once())->method('buildViolation')->willReturn($violationBuilder);

        $validator = new AtLeastOneAdminValidator($repository);
        $validator->initialize($context);

        $validator->validate(['ROLE_USER'], new AtLeastOneAdmin());
    }
}
