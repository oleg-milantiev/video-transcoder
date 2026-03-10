<?php

namespace App\Presentation\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Attribute\Route;
use TusPhp\Tus\Server as TusServer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class UploadController extends AbstractController
{
    #[Route('/upload/{token?}', name: 'tus', defaults: ['token' => ''])]
    public function uploadHandler(
        TusServer $server,
        EventDispatcherInterface $symfonyDispatcher,
        LoggerInterface $logger,
    ): Response
    {
        $server->setDispatcher($symfonyDispatcher);

        // TODO rename file to random uniq (by tus uuid?)
        // TODO call TusPhp\Commands\ExpirationCommand (cron?)
        return $server->serve();
    }
}
