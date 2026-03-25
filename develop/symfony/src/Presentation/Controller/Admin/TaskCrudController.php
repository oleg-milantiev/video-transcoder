<?php

namespace App\Presentation\Controller\Admin;

use App\Domain\Video\ValueObject\TaskStatus;
use App\Infrastructure\Persistence\Doctrine\Task\TaskEntity;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;

class TaskCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return TaskEntity::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setEntityLabelInSingular('Task')
            ->setEntityLabelInPlural('Tasks');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status')->setChoices(array_flip(TaskStatus::NAMES)))
            ->add(NumericFilter::new('progress'))
            ->add(EntityFilter::new('user'))
            ->add(EntityFilter::new('video'))
            ->add(EntityFilter::new('preset'))
            ->add(DateTimeFilter::new('createdAt'))
            ->add(DateTimeFilter::new('startedAt'));
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW)
            ->disable(Action::DELETE)
            ->disable(Action::EDIT);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('id')
                ->hideOnForm()
                ->formatValue(static fn ($value) => is_object($value) && method_exists($value, 'toRfc4122') ? $value->toRfc4122() : (string) $value),
            ChoiceField::new('status')
                ->setChoices(array_flip(TaskStatus::NAMES)),
            IntegerField::new('progress')->setFormTypeOptions([
                'attr' => [
                    'min' => 0,
                    'max' => 100,
                ],
            ]),
            AssociationField::new('user'),
            AssociationField::new('video'),
            AssociationField::new('preset'),
            ArrayField::new('meta')
                ->setTemplatePath('admin/field/associative_array_detail.html.twig')
                ->onlyOnDetail(),
            DateTimeField::new('createdAt')->hideOnForm(),
            DateTimeField::new('updatedAt')->hideOnForm(),
            DateTimeField::new('startedAt')->hideOnForm(),
        ];
    }
}
