<?php
declare(strict_types=1);

namespace App\Presentation\Controller\Admin;

use App\Domain\User\ValueObject\PaymentGateway;
use App\Domain\User\ValueObject\PaymentStatus;
use App\Infrastructure\Persistence\Doctrine\Payment\PaymentEntity;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class PaymentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return PaymentEntity::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setEntityLabelInSingular('Payment')
            ->setEntityLabelInPlural('Payments')
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW)
            ->disable(Action::EDIT)
            ->disable(Action::DELETE);
    }

    public function configureFilters(Filters $filters): Filters
    {
        $statusChoices  = array_flip(PaymentStatus::NAMES);
        $gatewayChoices = array_flip(PaymentGateway::NAMES);

        return $filters
            ->add(EntityFilter::new('user'))
            ->add(ChoiceFilter::new('status')->setChoices($statusChoices))
            ->add(ChoiceFilter::new('gateway')->setChoices($gatewayChoices))
            ->add(TextFilter::new('currency'))
            ->add(TextFilter::new('externalId')->setLabel('External ID'))
            ->add(TextFilter::new('paymentMethod')->setLabel('Payment Method'))
            ->add(TextFilter::new('planSnapshot')->setLabel('Plan'))
            ->add(NumericFilter::new('amount')->setLabel('Amount (minor units)'))
            ->add(DateTimeFilter::new('createdAt'))
            ->add(DateTimeFilter::new('paidAt'))
            ->add(DateTimeFilter::new('validUntil'));
    }

    public function configureFields(string $pageName): iterable
    {
        $statusChoices  = array_flip(PaymentStatus::NAMES);
        $gatewayChoices = array_flip(PaymentGateway::NAMES);

        return [
            TextField::new('id')
                ->hideOnForm()
                ->formatValue(static fn ($v) => is_object($v) && method_exists($v, 'toRfc4122') ? $v->toRfc4122() : (string) $v),

            AssociationField::new('user'),

            ChoiceField::new('status')
                ->setChoices($statusChoices)
                ->renderAsBadges([
                    'pending'   => 'warning',
                    'completed' => 'success',
                    'failed'    => 'danger',
                    'refunded'  => 'info',
                    'cancelled' => 'secondary',
                ]),

            ChoiceField::new('gateway')
                ->setChoices($gatewayChoices),

            TextField::new('currency'),

            IntegerField::new('amount')
                ->setLabel('Amount (minor units)'),

            TextField::new('planSnapshot')
                ->setLabel('Plan'),

            TextField::new('externalId')
                ->setLabel('External ID')
                ->hideOnIndex(),

            TextField::new('paymentMethod')
                ->setLabel('Method')
                ->hideOnIndex(),

            UrlField::new('invoiceUrl')
                ->setLabel('Invoice')
                ->hideOnIndex(),

            ArrayField::new('meta')
                ->setTemplatePath('admin/field/associative_array_detail.html.twig')
                ->onlyOnDetail(),

            DateTimeField::new('createdAt')
                ->hideOnForm(),

            DateTimeField::new('paidAt')
                ->hideOnForm(),

            DateTimeField::new('validUntil')
                ->hideOnForm(),
        ];
    }
}
