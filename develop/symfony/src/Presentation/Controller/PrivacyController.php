<?php
declare(strict_types=1);

namespace App\Presentation\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PrivacyController extends AbstractController
{
    #[Route('/privacy', name: 'privacy', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('privacy.html.twig');
    }
}
