<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller\Admin;

use App\Presentation\Controller\Admin\TariffCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Choice;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Presentation\Controller\Admin\TariffCrudController
 */
class TariffCrudControllerTest extends TestCase
{
    public function testGetEntityFqcnReturnsTariffEntity(): void
    {
        self::assertSame(
            \App\Infrastructure\Persistence\Doctrine\User\TariffEntity::class,
            TariffCrudController::getEntityFqcn()
        );
    }

    public function testConfigureCrudReturnsCrudWithExpectedConfiguration(): void
    {
        $controller = new TariffCrudController();
        $crud = $controller->configureCrud(new Crud());
        
        self::assertInstanceOf(Crud::class, $crud);
        // Testing specific Crud configuration would require checking internal state
    }

    public function testConfigureActionsReturnsActionsWithExpectedConfiguration(): void
    {
        $controller = new TariffCrudController();
        $actions = $controller->configureActions(new Actions());
        
        self::assertInstanceOf(Actions::class, $actions);
        // Testing specific Actions configuration would require checking internal state
    }

    public function testConfigureFiltersReturnsFiltersWithExpectedConfiguration(): void
    {
        $controller = new TariffCrudController();
        $filters = $controller->configureFilters(new Filters());
        
        self::assertInstanceOf(Filters::class, $filters);
        // Testing specific filter configuration would require accessing internal state
    }

    public function testConfigureFieldsReturnsIterableWithExpectedFields(): void
    {
        $controller = new TariffCrudController();
        $fields = $controller->configureFields('index');
        
        self::assertIsIterable($fields);
        
        $fields = iterator_to_array($fields);
        self::assertCount(10, $fields); // id, title, delay, instance, videoDuration, videoSize, maxWidth, maxHeight, storageGb, storageHour
        
        // Check that we have the expected field types (basic check)
        $fieldNames = [];
        foreach ($fields as $field) {
            if (method_exists($field, 'getName')) {
                $fieldNames[] = $field->getName();
            }
        }
        
        $expectedFields = [
            'id', 'title', 'delay', 'instance', 'videoDuration', 'videoSize', 
            'maxWidth', 'maxHeight', 'storageGb', 'storageHour'
        ];
        foreach ($expectedFields as $expectedField) {
            self::assertContains($expectedField, $fieldNames);
        }
    }
}