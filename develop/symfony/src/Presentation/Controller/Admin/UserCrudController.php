<?php
declare(strict_types=1);

namespace App\Presentation\Controller\Admin;

use App\Domain\Shared\ValueObject\Uuid;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Persistence\Doctrine\User\UserRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;

class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return UserEntity::class;
    }

    /**
     * @throws Exception
     */
    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof UserEntity) {
            return;
        }

        if (in_array('ROLE_ADMIN', $entityInstance->getRoles(), true)) {
            $excludeId = Uuid::fromString($entityInstance->id->toRfc4122());

            if ($this->userRepository->countAdmins($excludeId) === 0) {
                $this->addFlash('danger', 'Cannot delete the last administrator.');
                return;
            }
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setEntityLabelInSingular('User')
            ->setEntityLabelInPlural('Users')
            ->setDefaultSort(['email' => 'asc']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('email'))
            ->add(EntityFilter::new('tariff'));
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('id')
                ->hideOnForm()
                ->formatValue(static fn ($value) => is_object($value) && method_exists($value, 'toRfc4122') ? $value->toRfc4122() : (string) $value),
            TextField::new('email'),
            TextField::new('plainPassword')
                ->setFormType(PasswordType::class)
                ->onlyOnForms(),
            ArrayField::new('roles'),
            AssociationField::new('tariff')
                ->setFormTypeOption('query_builder', static function(\Doctrine\ORM\EntityRepository $repository) {
                    return $repository->createQueryBuilder('t')->orderBy('t.title', 'ASC');
                }),
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof UserEntity && $entityInstance->plainPassword !== null && $entityInstance->plainPassword !== '') {
            $hashed = $this->passwordHasher->hashPassword($entityInstance, $entityInstance->plainPassword);
            $entityInstance->setPassword($hashed);
            $entityInstance->plainPassword = null;
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof UserEntity && $entityInstance->plainPassword !== null && $entityInstance->plainPassword !== '') {
            $hashed = $this->passwordHasher->hashPassword($entityInstance, $entityInstance->plainPassword);
            $entityInstance->setPassword($hashed);
            $entityInstance->plainPassword = null;
        }

        parent::updateEntity($entityManager, $entityInstance);
    }
}
