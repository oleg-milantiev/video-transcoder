<?php
declare(strict_types=1);

namespace App\Presentation\Controller\Admin;

use App\Infrastructure\Persistence\Doctrine\Preset\PresetEntity;
use App\Infrastructure\Persistence\Doctrine\User\TariffEntity;
use App\Presentation\Validator\PresetBitrateJsonConstraint;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

class PresetCrudController extends AbstractCrudController
{
    private const array BITRATE_DEFAULT = [
        '144'  => 0.1,
        '240'  => 0.4,
        '360'  => 1.0,
        '480'  => 2.5,
        '720'  => 5.0,
        '1080' => 8.0,
        '1440' => 16.0,
        '2160' => 35.0,
        '4320' => 85.0,
    ];

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
        $bitrateDefault = json_encode(self::BITRATE_DEFAULT, JSON_PRETTY_PRINT);

        yield TextField::new('id')
            ->hideOnForm()
            ->formatValue(static fn ($value) => is_object($value) && method_exists($value, 'toRfc4122') ? $value->toRfc4122() : (string) $value);
        yield TextField::new('format');
        yield TextField::new('videoCodec');
        yield TextField::new('audioCodec');

        if (in_array($pageName, [Crud::PAGE_NEW, Crud::PAGE_EDIT], true)) {
            yield CodeEditorField::new('bitrateJson', 'Bitrate (JSON, height → Mbps)')
                ->setHelp('JSON object mapping resolution height (integer) to bitrate in Mbps (float). Example: {"720": 5.0}')
                ->setFormTypeOptions([
                    'mapped'      => false,
                    'required'    => false,
                    'constraints' => [new PresetBitrateJsonConstraint()],
                    'attr'        => ['rows' => 12],
                    'data'        => $bitrateDefault,
                ])
                ->setLanguage('javascript');
        } else {
            yield CodeEditorField::new('bitrateJson', 'Bitrate')
                ->hideOnForm()
                ->setLanguage('javascript');
        }

        yield AssociationField::new('tariffs')
            ->setLabel('Tariffs')
            ->onlyOnForms()
            ->setFormTypeOptions(['by_reference' => false]);
        yield AssociationField::new('tariffs')
            ->setLabel('Tariffs')
            ->setTemplatePath('admin/field/preset_tariffs_summary.html.twig')
            ->formatValue(fn ($value, ?PresetEntity $entity) => [
                'tariffs' => $this->collectTariffLinks($entity),
            ])
            ->onlyOnIndex();
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->applyBitrateFromForm($entityInstance);
        $this->syncTariffsInverseSide($entityManager, $entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->applyBitrateFromForm($entityInstance);
        $this->syncTariffsInverseSide($entityManager, $entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    private function applyBitrateFromForm(mixed $entity): void
    {
        if (!$entity instanceof PresetEntity) {
            return;
        }

        $context = $this->getContext();
        if ($context === null) {
            return;
        }

        $form = $context->getRequest()->request->all();
        $bitrateJson = $form['PresetEntity']['bitrateJson'] ?? null;

        if ($bitrateJson === null || trim((string) $bitrateJson) === '') {
            return;
        }

        $decoded = json_decode((string) $bitrateJson, true);
        if (is_array($decoded)) {
            $entity->bitrate = $decoded;
        }
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
                    'url'   => $this->buildTariffUrl($tariff->id->toRfc4122()),
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

    private function syncTariffsInverseSide(EntityManagerInterface $em, mixed $entity): void
    {
        if (!$entity instanceof PresetEntity) {
            return;
        }

        $desiredTariffIds = [];
        foreach ($entity->tariffs as $tariff) {
            if ($tariff->id !== null) {
                $desiredTariffIds[$tariff->id->toRfc4122()] = true;
            }
        }

        if ($entity->id !== null) {
            $tariffsWithThisPreset = $em->createQuery(
                'SELECT t FROM ' . TariffEntity::class . ' t JOIN t.presets p WHERE p.id = :presetId'
            )
                ->setParameter('presetId', $entity->id)
                ->getResult();

            foreach ($tariffsWithThisPreset as $tariff) {
                $id = $tariff->id->toRfc4122();
                if (!isset($desiredTariffIds[$id])) {
                    $tariff->presets->removeElement($entity);
                }
            }
        }
        foreach ($entity->tariffs as $tariff) {
            if (!$tariff->presets->contains($entity)) {
                $tariff->presets->add($entity);
            }
        }
    }
}
