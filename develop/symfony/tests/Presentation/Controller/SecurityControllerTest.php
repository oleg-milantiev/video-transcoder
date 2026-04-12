<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller;

use App\Presentation\Controller\SecurityController;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityControllerTest extends WebTestCase
{
    public function testLoginPageRendersSuccessfully(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testLogoutMethodThrowsLogicException(): void
    {
        $controller = new SecurityController();

        $this->expectException(\LogicException::class);
        $controller->logout();
    }
}
