<?php
declare(strict_types=1);

namespace App\Presentation\Controller\Admin;

use App\Infrastructure\Persistence\Doctrine\Preset\PresetEntity;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Doctrine\ORM\EntityManagerInterface;

class PresetCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return PresetEntity::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setEntityLabelInSingular('Preset')
            ->setEntityLabelInPlural('Presets')
            ->setDefaultSort(['videoCodec' => 'ASC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('format'))
            ->add(TextFilter::new('videoCodec'))
            ->add(TextFilter::new('audioCodec'));
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('id')
                ->hideOnForm()
                ->formatValue(static fn ($value) => is_object($value) && method_exists($value, 'toRfc4122') ? $value->toRfc4122() : (string) $value),
            TextField::new('format'),
            TextField::new('videoCodec'),
            TextField::new('audioCodec'),
            AssociationField::new('tariffs')
                ->setLabel('Tariffs')
                ->onlyOnForms()
                ->setFormTypeOptions(['by_reference' => false]),
            AssociationField::new('tariffs')
                ->setLabel('Tariffs')
                ->setTemplatePath('admin/field/preset_tariffs_summary.html.twig')
                ->formatValue(fn ($value, ?PresetEntity $entity) => [
                    'tariffs' => $this->collectTariffLinks($entity),
                ])
                ->onlyOnIndex(),
        ];
    }

    private function collectTariffLinks(?PresetEntity $preset): array
    {
        if (null === $preset) {
            return [];
        }

        $tariffs = [];
        foreach ($preset->tariffs as $tariff) {
            if (null !== $tariff?->id) {
                $id = $tariff->id->toRfc4122();
                $tariffs[$id] = [
                    'title' => (string) $tariff,
                    'url' => $this->buildTariffUrl($tariff->id->toRfc4122()),
                ];
            }
        }

        return array_values($tariffs);
    }

    private function buildTariffUrl(string $tariffId): ?string
    {
        return $this->adminUrlGenerator
            ->unsetAll()
            ->setController(TariffCrudController::class)
            ->setAction(Crud::PAGE_DETAIL)
            ->setEntityId($tariffId)
            ->generateUrl();
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        foreach ($entityInstance->tariffs as $tariff) {
            if (!$tariff->presets->contains($entityInstance)) {
                $tariff->presets->add($entityInstance);
            }
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        foreach ($entityInstance->tariffs as $tariff) {
            if (!$tariff->presets->contains($entityInstance)) {
                $tariff->presets->add($entityInstance);
            }
        }

        parent::persistEntity($entityManager, $entityInstance);
    }
}
