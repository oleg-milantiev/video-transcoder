<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller\Admin;

use App\Presentation\Controller\Admin\PresetCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Choice;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Presentation\Controller\Admin\PresetCrudController
 */
class PresetCrudControllerTest extends TestCase
{
    public function testGetEntityFqcnReturnsPresetEntity(): void
    {
        self::assertSame(
            \App\Infrastructure\Persistence\Doctrine\Preset\PresetEntity::class,
            PresetCrudController::getEntityFqcn()
        );
    }

    public function testConfigureCrudReturnsCrudWithExpectedConfiguration(): void
    {
        $controller = new PresetCrudController();
        $crud = $controller->configureCrud($this->getMockBuilder(\EasyCorp\Bundle\EasyAdminBundle\Config\Crud::class)
            ->disableOriginalConstructor()
            ->getMock());
        
        self::assertInstanceOf(\EasyCorp\Bundle\EasyAdminBundle\Config\Crud::class, $crud);
    }

    public function testConfigureActionsReturnsActionsWithExpectedConfiguration(): void
    {
        $controller = new PresetCrudController();
        $actions = $controller->configureActions($this->getMockBuilder(\EasyCorp\Bundle\EasyAdminBundle\Config\Actions::class)
            ->disableOriginalConstructor()
            ->getMock());
        
        self::assertInstanceOf(\EasyCorp\Bundle\EasyAdminBundle\Config\Actions::class, $actions);
    }

    public function testConfigureFiltersReturnsFiltersWithExpectedConfiguration(): void
    {
        $controller = new PresetCrudController();
        $filters = $controller->configureFilters($this->getMockBuilder(\EasyCorp\Bundle\EasyAdminBundle\Config\Filters::class)
            ->disableOriginalConstructor()
            ->getMock());
        
        self::assertInstanceOf(\EasyCorp\Bundle\EasyAdminBundle\Config\Filters::class, $filters);
    }

    public function testConfigureFieldsReturnsIterableWithExpectedFields(): void
    {
        $controller = new PresetCrudController();
        $fields = $controller->configureFields('index');
        
        self::assertIsIterable($fields);
        
        $fields = iterator_to_array($fields);
        self::assertCount(6, $fields); // id, title, width, height, codec, bitrate
        
        // Check that we have the expected field types (basic check)
        $fieldNames = [];
        foreach ($fields as $field) {
            if (method_exists($field, 'getName')) {
                $fieldNames[] = $field->getName();
            }
        }
        
        $expectedFields = ['id', 'title', 'width', 'height', 'codec', 'bitrate'];
        foreach ($expectedFields as $expectedField) {
            self::assertContains($expectedField, $fieldNames);
        }
    }
}