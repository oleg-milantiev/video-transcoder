<?php

namespace App\Presentation\Controller\Admin;

use App\Infrastructure\Persistence\Doctrine\Video\VideoEntity;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

class VideoCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return VideoEntity::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setEntityLabelInSingular('Video')
            ->setEntityLabelInPlural('Videos');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('title'))
            ->add(EntityFilter::new('user'))
            ->add(DateTimeFilter::new('createdAt'));
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW)
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action->displayIf(static function (VideoEntity $entity) {
                    return $entity->tasks->isEmpty();
                });
            })
            ->update(Crud::PAGE_DETAIL, Action::DELETE, function (Action $action) {
                return $action->displayIf(static function (VideoEntity $entity) {
                    return $entity->tasks->isEmpty();
                });
            });
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('id')
                ->hideOnForm()
                ->formatValue(static fn ($value) => is_object($value) && method_exists($value, 'toRfc4122') ? $value->toRfc4122() : (string) $value),
            TextField::new('title'),
            AssociationField::new('user'),
            ArrayField::new('meta')
                ->setTemplatePath('admin/field/associative_array_detail.html.twig')
                ->onlyOnDetail(),
            DateTimeField::new('createdAt')->hideOnForm(),
            DateTimeField::new('updatedAt')->hideOnForm(),
            AssociationField::new('tasks')
                ->setTemplatePath('admin/field/video_tasks_summary.html.twig')
                ->formatValue(fn ($value, ?VideoEntity $entity) => [
                    'presets' => $this->collectPresetLinks($entity),
                ])
                ->hideOnForm(),
        ];
    }

    private function collectPresetLinks(?VideoEntity $video): array
    {
        if (null === $video) {
            return [];
        }

        $presets = [];
        foreach ($video->tasks as $task) {
            $preset = $task->preset;
            if (null !== $preset?->id && '' !== trim($preset->title)) {
                $id = $preset->id->toRfc4122();
                $presets[$id] = [
                    'title' => $preset->title,
                    'url' => $this->buildTasksUrl($video, $id),
                ];
            }
        }

        return array_values($presets);
    }

    private function buildTasksUrl(?VideoEntity $video, ?string $presetId = null): ?string
    {
        if (null === $video?->id) {
            return null;
        }

        $urlGenerator = $this->adminUrlGenerator
            ->unsetAll()
            ->setController(TaskCrudController::class)
            ->setAction(Crud::PAGE_INDEX)
            ->set('filters[video][comparison]', '=')
            ->set('filters[video][value]', $video->id->toRfc4122());

//        if (null !== $presetId) {
//            $urlGenerator
//                ->set('filters[preset][comparison]', '=')
//                ->set('filters[preset][value]', $presetId);
//        }

        return $urlGenerator->generateUrl();
    }
}
