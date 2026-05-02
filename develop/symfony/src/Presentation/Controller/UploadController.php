<?php
declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Application\Factory\VideoFactory;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use TusPhp\Tus\Server as TusServer;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class UploadController extends AbstractController
{
    public function __construct(
        private readonly VideoFactory $videoFactory,
        private readonly VideoRepositoryInterface $videoRepository,
    ) {
    }

    /**
     * Handles all TUS resumable upload protocol requests (HEAD, POST, PATCH, DELETE).
     * On a new upload (POST, 201 Created) a Video entity with loading=true is created
     * immediately and its id is stored inside the TUS file cache so that
     * TusPostFinishListener can find it when the upload completes.
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

        $response = $server->serve();

        // When TUS creates a new upload resource (201) we attach a Video entity to it
        if ($response->getStatusCode() === Response::HTTP_CREATED) {
            $this->createVideoForTusUpload($server, $response);
        }

        return $response;
    }

    /**
     * Creates a Video(loading=true) and stores its id in the TUS file cache entry
     * so that TusPostFinishListener can retrieve it later.
     */
    private function createVideoForTusUpload(TusServer $server, Response $response): void
    {
        $location = $response->headers->get('location', '');
        $tusKey = basename($location);
        if ($tusKey === '') {
            return;
        }

        $fileData = $server->getCache()->get($tusKey);
        if (!is_array($fileData)) {
            return;
        }

        $user = $this->getUser();
        if ($user === null) {
            return;
        }

        try {
            // todo проверить где имя передаётся, только его использовать
            $filename = $fileData['metadata']['originalName']
                ?? $fileData['metadata']['filename']
                ?? $fileData['name'];

            $userId = Uuid::fromString($user->id->toRfc4122());
            $video = $this->videoFactory->fromFilename($filename, $userId);
            $video = $this->videoRepository->save($video);

            $fileData['videoId'] = $video->id()->toRfc4122();
            $server->getCache()->set($tusKey, $fileData);
        } catch (\Throwable) {
            // If video creation fails the upload still proceeds;
            // TusPostFinishListener will fall back to creating a new Video.
        }
    }
}
