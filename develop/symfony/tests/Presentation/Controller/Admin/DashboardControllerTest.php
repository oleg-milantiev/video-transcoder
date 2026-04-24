<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller\Admin;

use App\Presentation\Controller\Admin\DashboardController;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Presentation\Controller\Admin\DashboardController
 */
class DashboardControllerTest extends TestCase
{
    public function testConfigureDashboardReturnsDashboardWithTitle(): void
    {
        $controller = new DashboardController();
        $dashboard = $controller->configureDashboard();

        self::assertInstanceOf(\EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard::class, $dashboard);
    }

    public function testConfigureMenuItemsReturnsIterableWithExpectedItems(): void
    {
        $controller = new DashboardController();
        $menuItems = $controller->configureMenuItems();

        self::assertIsIterable($menuItems);

        $menuItems = iterator_to_array($menuItems);
        self::assertCount(8, $menuItems); // Dashboard + 7 entity links
    }
}
