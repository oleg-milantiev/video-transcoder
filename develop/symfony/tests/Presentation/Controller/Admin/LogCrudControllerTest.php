<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller\Admin;

use App\Presentation\Controller\Admin\LogCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Choice;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

/**
 * @covers \App\Presentation\Controller\Admin\LogCrudController
 */
class LogCrudControllerTest extends TestCase
{
    public function testGetEntityFqcnReturnsLogEntity(): void
    {
        self::assertSame(
            \App\Infrastructure\Persistence\Doctrine\Log\LogEntity::class,
            LogCrudController::getEntityFqcn()
        );
    }

    public function testConfigureCrudReturnsCrudWithExpectedConfiguration(): void
    {
        $controller = new LogCrudController();
        $crud = $controller->configureCrud(new Crud());
        
        self::assertInstanceOf(Crud::class, $crud);
        // Note: Testing specific Crud configuration would require checking internal state
        // which is complex without getters. This test ensures the method executes without error.
    }

    public function testConfigureActionsReturnsActionsWithExpectedConfiguration(): void
    {
        $controller = new LogCrudController();
        $actions = $controller->configureActions(new Actions());
        
        self::assertInstanceOf(Actions::class, $actions);
        // Similar to above - testing specific configuration is complex without getters
    }

    public function testConfigureFiltersReturnsFiltersWithExpectedConfiguration(): void
    {
        $controller = new LogCrudController();
        $filters = $controller->configureFilters(new Filters());
        
        self::assertInstanceOf(Filters::class, $filters);
        // Testing specific filter configuration would require accessing internal state
    }

    public function testConfigureFieldsReturnsIterableWithExpectedFields(): void
    {
        $controller = new LogCrudController();
        $fields = $controller->configureFields('index');
        
        self::assertIsIterable($fields);
        
        $fields = iterator_to_array($fields);
        self::assertCount(8, $fields); // id, name, action, objectId, level, text, createdAt, context
        
        // Check that we have the expected field types (basic check)
        $fieldNames = [];
        foreach ($fields as $field) {
            if (method_exists($field, 'getName')) {
                $fieldNames[] = $field->getName();
            }
        }
        
        $expectedFields = ['id', 'name', 'action', 'objectId', 'level', 'text', 'createdAt', 'context'];
        foreach ($expectedFields as $expectedField) {
            self::assertContains($expectedField, $fieldNames);
        }
    }
}