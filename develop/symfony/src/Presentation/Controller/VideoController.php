<?php
declare(strict_types=1);

namespace App\Presentation\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class VideoController extends SPAController
{
    /**
     * Renders the video details SPA page for the given video UUID.
     * The UUID is passed into the SPA config so the Vue frontend can load the correct video.
     */
    #[Route('/video/{uuid}', name: 'video_details', requirements: ['uuid' => '[0-9a-fA-F-]{36}'], methods: ['GET'])]
    public function details(string $uuid): Response
    {
        return $this->render('video/details.html.twig', [
            'config' => array_merge($this->getSPA(), [
                'videoUuid' => $uuid,
            ]),
        ]);
    }
}
