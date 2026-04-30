<?php
declare(strict_types=1);

namespace App\Presentation\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends SPAController
{
    /**
     * Renders the main SPA home page.
     * Injects authentication tokens, Mercure config, and tariff data as JSON into the template.
     */
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig', ['config' => $this->getSPA()]);
    }
}
