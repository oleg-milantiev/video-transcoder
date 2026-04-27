<?php
declare(strict_types=1);

namespace App\Presentation\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class TariffController extends SPAController
{
    #[Route('/tariffs', name: 'tariffs', methods: ['GET'])]
    public function tariffs(): Response
    {
        return $this->render('SPA.html.twig', [
            'title' => 'Tariff - Video Transcoder - 100 percent',
            'config' => $this->getSPA(),
        ]);
    }
}
