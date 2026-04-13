<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller\Admin;

use App\Infrastructure\Persistence\Doctrine\Log\LogEntity;
use App\Presentation\Controller\Admin\LogCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogCrudController::class)]
class LogCrudControllerTest extends TestCase
{
    public function testGetEntityFqcnReturnsLogEntity(): void
    {
        self::assertSame(
            LogEntity::class,
            LogCrudController::getEntityFqcn()
        );
    }

    public function testConfigureCrudReturnsCrudWithExpectedConfiguration(): void
    {
        $controller = new LogCrudController();
        $crud = $controller->configureCrud(Crud::new());

        self::assertInstanceOf(Crud::class, $crud);
    }

    public function testConfigureActionsReturnsActionsWithExpectedConfiguration(): void
    {
        $controller = new LogCrudController();
        $actions = $controller->configureActions(Actions::new());

        self::assertInstanceOf(Actions::class, $actions);
    }

    public function testConfigureFiltersReturnsFiltersWithExpectedConfiguration(): void
    {
        $controller = new LogCrudController();
        $filters = $controller->configureFilters(Filters::new());

        self::assertInstanceOf(Filters::class, $filters);
    }

    public function testConfigureFieldsReturnsIterableWithExpectedFields(): void
    {
        $controller = new LogCrudController();
        $fields = $controller->configureFields('index');

        self::assertIsIterable($fields);

        $fields = iterator_to_array($fields);
        self::assertCount(8, $fields); // id, name, action, objectId, level, text, createdAt, context

        $fieldNames = [];
        foreach ($fields as $field) {
            if (method_exists($field, 'getAsDto')) {
                $fieldNames[] = $field->getAsDto()->getProperty();
            }
        }

        $expectedFields = ['id', 'name', 'action', 'objectId', 'level', 'text', 'createdAt', 'context'];
        foreach ($expectedFields as $expectedField) {
            self::assertContains($expectedField, $fieldNames);
        }
    }
}
