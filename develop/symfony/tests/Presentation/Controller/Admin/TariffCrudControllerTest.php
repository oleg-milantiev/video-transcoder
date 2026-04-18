<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller\Admin;

use App\Infrastructure\Persistence\Doctrine\User\TariffEntity;
use App\Presentation\Controller\Admin\TariffCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Registry\AdminControllerRegistryInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Router\AdminRouteGeneratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[CoversClass(TariffCrudController::class)]
class TariffCrudControllerTest extends TestCase
{
    private function createController(): TariffCrudController
    {
        $adminUrlGenerator = new AdminUrlGenerator(
            $this->createStub(AdminContextProviderInterface::class),
            $this->createStub(UrlGeneratorInterface::class),
            $this->createStub(AdminControllerRegistryInterface::class),
            $this->createStub(AdminRouteGeneratorInterface::class),
            $this->createStub(CacheItemPoolInterface::class),
        );
        return new TariffCrudController($adminUrlGenerator);
    }

    public function testGetEntityFqcnReturnsTariffEntity(): void
    {
        self::assertSame(
            TariffEntity::class,
            TariffCrudController::getEntityFqcn()
        );
    }

    public function testConfigureCrudReturnsCrudWithExpectedConfiguration(): void
    {
        $controller = $this->createController();
        $crud = $controller->configureCrud(Crud::new());

        self::assertInstanceOf(Crud::class, $crud);
    }

    public function testConfigureActionsReturnsActionsWithExpectedConfiguration(): void
    {
        $controller = $this->createController();
        $actions = $controller->configureActions(Actions::new());

        self::assertInstanceOf(Actions::class, $actions);
    }

    public function testConfigureFiltersReturnsFiltersWithExpectedConfiguration(): void
    {
        $controller = $this->createController();
        $filters = $controller->configureFilters(Filters::new());

        self::assertInstanceOf(Filters::class, $filters);
    }

    public function testConfigureFieldsReturnsIterableWithExpectedFields(): void
    {
        $controller = $this->createController();
        $fields = $controller->configureFields('index');

        self::assertIsIterable($fields);

        $fields = iterator_to_array($fields);
        self::assertCount(12, $fields); // id, title, delay, instance, videoDuration, videoSize, maxWidth, maxHeight, storageGb, storageHour, presets (form), presets (index)

        $fieldNames = [];
        foreach ($fields as $field) {
            if (method_exists($field, 'getAsDto')) {
                $fieldNames[] = $field->getAsDto()->getProperty();
            }
        }

        $expectedFields = [
            'id', 'title', 'delay', 'instance', 'videoDuration', 'videoSize',
            'maxWidth', 'maxHeight', 'storageGb', 'storageHour', 'presets',
        ];
        foreach ($expectedFields as $expectedField) {
            self::assertContains($expectedField, $fieldNames);
        }
    }
}
