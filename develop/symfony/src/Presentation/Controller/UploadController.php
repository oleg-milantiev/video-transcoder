<?php
declare(strict_types=1);

namespace App\Presentation\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use TusPhp\Tus\Server as TusServer;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class UploadController extends AbstractController
{
    /**
     * Handles all TUS resumable upload protocol requests (HEAD, POST, PATCH, DELETE).
     * The route captures the optional TUS upload token in the URL so that continuation
     * requests reach the same server instance. Ensures the upload directory exists
     * before delegating to the TUS server.
     */
    #[Route('/api/upload/{token?}', name: 'api_tus', defaults: ['token' => ''])]
    public function uploadHandler(
        TusServer $server,
        EventDispatcherInterface $symfonyDispatcher,
    ): Response {
        if (!is_dir($server->getUploadDir())) {
            mkdir($server->getUploadDir());
        }

        $server->setDispatcher($symfonyDispatcher);

        return $server->serve();
    }
}
