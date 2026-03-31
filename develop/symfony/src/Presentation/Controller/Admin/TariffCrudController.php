<?php
declare(strict_types=1);

namespace App\Presentation\Controller\Admin;

use App\Infrastructure\Persistence\Doctrine\User\TariffEntity;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class TariffCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return TariffEntity::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setEntityLabelInSingular('Tariff')
            ->setEntityLabelInPlural('Tariffs')
            ->setDefaultSort(['title' => 'ASC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('title'))
            ->add(NumericFilter::new('delay'))
            ->add(NumericFilter::new('instance'))
            ->add(NumericFilter::new('videoDuration'))
            ->add(NumericFilter::new('videoSize'))
            ->add(NumericFilter::new('maxWidth'))
            ->add(NumericFilter::new('maxHeight'))
            ->add(NumericFilter::new('storageGb'))
            ->add(NumericFilter::new('storageHour'));
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('id')
                ->hideOnForm()
                ->formatValue(static fn ($value) => is_object($value) && method_exists($value, 'toRfc4122') ? $value->toRfc4122() : (string) $value),
            TextField::new('title'),
            IntegerField::new('delay', 'Delay (sec)')
                ->setHelp('Minimum seconds between tasks. 0 = no limit.'),
            IntegerField::new('instance', 'Parallel tasks')
                ->setHelp('Maximum number of simultaneously running tasks. At least 1.'),
            IntegerField::new('videoDuration', 'Max video duration (sec)')
                ->setHelp('Maximum allowed source video duration in seconds. At least 1.'),
            NumberField::new('videoSize', 'Max video size (MB)')
                ->setHelp('Maximum allowed source file size in megabytes. Must be > 0.')
                ->setNumDecimals(2),
            IntegerField::new('maxWidth', 'Max width (px)')
                ->setHelp('Maximum allowed output width in pixels. At least 1.'),
            IntegerField::new('maxHeight', 'Max height (px)')
                ->setHelp('Maximum allowed output height in pixels. At least 1.'),
            NumberField::new('storageGb', 'Storage (GB)')
                ->setHelp('Total storage quota in gigabytes. Must be > 0.')
                ->setNumDecimals(2),
            IntegerField::new('storageHour', 'Storage retention (hours)')
                ->setHelp('How many hours uploaded files are kept. At least 1.'),
        ];
    }
}
