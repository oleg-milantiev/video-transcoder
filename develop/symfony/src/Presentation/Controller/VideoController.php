<?php

namespace App\Presentation\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class VideoController extends SPAController
{
    #[Route('/video/{uuid}', name: 'video_details', requirements: ['uuid' => '[0-9a-fA-F-]{36}'], methods: ['GET'])]
    public function details(string $uuid): Response
    {
        return $this->render('video/details.html.twig', array_merge($this->getSPA(), [
            'uuid' => $uuid,
        ]));
    }
}
