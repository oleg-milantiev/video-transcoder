<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller\Admin;

use App\Infrastructure\Persistence\Doctrine\Preset\PresetEntity;
use App\Presentation\Controller\Admin\PresetCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PresetCrudController::class)]
class PresetCrudControllerTest extends TestCase
{
    public function testGetEntityFqcnReturnsPresetEntity(): void
    {
        self::assertSame(
            PresetEntity::class,
            PresetCrudController::getEntityFqcn()
        );
    }

    public function testConfigureCrudReturnsCrudWithExpectedConfiguration(): void
    {
        $controller = new PresetCrudController();
        $crud = $controller->configureCrud(Crud::new());

        self::assertInstanceOf(Crud::class, $crud);
    }

    public function testConfigureActionsReturnsActionsWithExpectedConfiguration(): void
    {
        $controller = new PresetCrudController();
        $actions = $controller->configureActions(Actions::new());

        self::assertInstanceOf(Actions::class, $actions);
    }

    public function testConfigureFiltersReturnsFiltersWithExpectedConfiguration(): void
    {
        $controller = new PresetCrudController();
        $filters = $controller->configureFilters(Filters::new());

        self::assertInstanceOf(Filters::class, $filters);
    }

    public function testConfigureFieldsReturnsIterableWithExpectedFields(): void
    {
        $controller = new PresetCrudController();
        $fields = $controller->configureFields('index');

        self::assertIsIterable($fields);

        $fields = iterator_to_array($fields);
        self::assertCount(8, $fields); // id, title, width, height, videoCodec, audioCodec, format, bitrate

        $fieldNames = [];
        foreach ($fields as $field) {
            if (method_exists($field, 'getAsDto')) {
                $fieldNames[] = $field->getAsDto()->getProperty();
            }
        }

        $expectedFields = ['id', 'title', 'width', 'height', 'videoCodec', 'audioCodec', 'format', 'bitrate'];
        foreach ($expectedFields as $expectedField) {
            self::assertContains($expectedField, $fieldNames);
        }
    }
}
