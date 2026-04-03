<?php
declare(strict_types=1);

namespace App\Presentation\Controller\Admin;

use App\Infrastructure\Persistence\Doctrine\Log\LogEntity;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Psr\Log\LogLevel;

class LogCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return LogEntity::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setEntityLabelInSingular('Log')
            ->setEntityLabelInPlural('Logs')
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW)
            ->disable(Action::DELETE)
            ->disable(Action::EDIT);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name'))
            ->add(TextFilter::new('action'))
            ->add(TextFilter::new('objectId'))
            ->add(ChoiceFilter::new('level')->setChoices([
                LogLevel::EMERGENCY => LogLevel::EMERGENCY,
                LogLevel::ALERT => LogLevel::ALERT,
                LogLevel::CRITICAL => LogLevel::CRITICAL,
                LogLevel::ERROR => LogLevel::ERROR,
                LogLevel::WARNING => LogLevel::WARNING,
                LogLevel::NOTICE => LogLevel::NOTICE,
                LogLevel::INFO => LogLevel::INFO,
                LogLevel::DEBUG => LogLevel::DEBUG,
            ]))
            ->add(TextFilter::new('text'))
            ->add(DateTimeFilter::new('createdAt'));
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('id')
                ->hideOnForm()
                ->formatValue(static fn ($value) => is_object($value) && method_exists($value, 'toRfc4122') ? $value->toRfc4122() : (string) $value),
            TextField::new('name'),
            TextField::new('objectId')
                ->formatValue(static fn ($value) => is_object($value) && method_exists($value, 'toRfc4122') ? $value->toRfc4122() : (string) $value),
            TextField::new('level'),
            TextField::new('text'),
            DateTimeField::new('createdAt')->hideOnForm(),
            ArrayField::new('context')
                ->setTemplatePath('admin/field/associative_array_detail.html.twig')
                ->onlyOnDetail(),
        ];
    }
}
