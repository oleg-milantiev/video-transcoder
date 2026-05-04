<?php
declare(strict_types=1);

namespace App\Presentation\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TermsController extends AbstractController
{
    /**
     * Renders the terms of service page.
     */
    #[Route('/terms', name: 'terms', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('terms.html.twig');
    }
}
