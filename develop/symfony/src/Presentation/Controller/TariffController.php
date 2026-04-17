<?php
declare(strict_types=1);

namespace App\Presentation\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TariffController extends SPAController
{
    #[Route('/tariffs', name: 'tariffs', methods: ['GET'])]
    public function tariffs(): Response
    {
        return $this->render('tariff/index.html.twig', [
            'config' => $this->getSPA(),
        ]);
    }
}
