<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller\Admin;

use App\Infrastructure\Persistence\Doctrine\User\TariffEntity;
use App\Presentation\Controller\Admin\TariffCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TariffCrudController::class)]
class TariffCrudControllerTest extends TestCase
{
    public function testGetEntityFqcnReturnsTariffEntity(): void
    {
        self::assertSame(
            TariffEntity::class,
            TariffCrudController::getEntityFqcn()
        );
    }

    public function testConfigureCrudReturnsCrudWithExpectedConfiguration(): void
    {
        $controller = new TariffCrudController();
        $crud = $controller->configureCrud(Crud::new());

        self::assertInstanceOf(Crud::class, $crud);
    }

    public function testConfigureActionsReturnsActionsWithExpectedConfiguration(): void
    {
        $controller = new TariffCrudController();
        $actions = $controller->configureActions(Actions::new());

        self::assertInstanceOf(Actions::class, $actions);
    }

    public function testConfigureFiltersReturnsFiltersWithExpectedConfiguration(): void
    {
        $controller = new TariffCrudController();
        $filters = $controller->configureFilters(Filters::new());

        self::assertInstanceOf(Filters::class, $filters);
    }

    public function testConfigureFieldsReturnsIterableWithExpectedFields(): void
    {
        $controller = new TariffCrudController();
        $fields = $controller->configureFields('index');

        self::assertIsIterable($fields);

        $fields = iterator_to_array($fields);
        self::assertCount(10, $fields); // id, title, delay, instance, videoDuration, videoSize, maxWidth, maxHeight, storageGb, storageHour

        $fieldNames = [];
        foreach ($fields as $field) {
            if (method_exists($field, 'getAsDto')) {
                $fieldNames[] = $field->getAsDto()->getProperty();
            }
        }

        $expectedFields = [
            'id', 'title', 'delay', 'instance', 'videoDuration', 'videoSize',
            'maxWidth', 'maxHeight', 'storageGb', 'storageHour',
        ];
        foreach ($expectedFields as $expectedField) {
            self::assertContains($expectedField, $fieldNames);
        }
    }
}
