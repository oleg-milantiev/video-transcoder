<?php
declare(strict_types=1);

namespace App\Presentation\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ProfileController extends SPAController
{
    /**
     * Renders the user profile SPA page.
     * The page uses the same shared SPA shell and receives the config payload required
     * by the Vue frontend (tokens, Mercure hub, tariff data).
     */
    #[Route('/profile', name: 'profile', methods: ['GET'])]
    public function profile(): Response
    {
        return $this->render('SPA.html.twig', [
            'title' => 'Profile - Video Transcoder - 100 percent',
            'config' => $this->getSPA(),
        ]);
    }
}
