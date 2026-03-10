<?php

namespace App\Presentation\Controller;

use Symfony\Component\Routing\Attribute\Route;
use TusPhp\Tus\Server as TusServer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class UploadController extends AbstractController
{
    public function __construct(
    ) {
    }

    #[Route('/upload/{token?}', name: 'tus', defaults: ['token' => ''])]
    public function uploadHandler(TusServer $server): Response
    {
        return $server->serve();
    }
}
