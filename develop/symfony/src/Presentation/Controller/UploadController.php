<?php
declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Application\Factory\FlashNotificationFactory;
use App\Application\Factory\VideoFactory;
use App\Application\Logging\LogServiceInterface;
use App\Application\Service\Mercure\FlashRealtimeNotifier;
use App\Domain\Video\Repository\StorageRepositoryInterface;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use App\Infrastructure\Persistence\Doctrine\User\UserMapper;
use Psr\Log\LogLevel;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use TusPhp\Tus\Server as TusServer;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class UploadController extends AbstractController
{
    public function __construct(
        private readonly FlashRealtimeNotifier $flashRealtimeNotifier,
        private readonly FlashNotificationFactory $flashNotificationFactory,
        private readonly LogServiceInterface $logService,
        private readonly VideoFactory $videoFactory,
        private readonly VideoRepositoryInterface $videoRepository,
        private readonly StorageRepositoryInterface $storageRepository,
    ) {
    }

    /**
     * Handles all TUS resumable upload protocol requests (HEAD, POST, PATCH, DELETE).
     * On a new upload (POST, 201 Created) a Video entity with loading=true is created
     * immediately and its id is stored inside the TUS file cache so that
     * TusPostFinishListener can find it when the upload completes.
     * Returns 422 if the file would exceed the user's storage quota.
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
            if (!$this->createVideoForTusUpload($server, $response)) {
                return new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        return $response;
    }

    /**
     * Creates a Video(loading=true) with meta['size'] from TUS upload-length,
     * checks storage quota, and stores the video id in the TUS file cache entry
     * so that TusPostFinishListener can retrieve it later.
     */
    private function createVideoForTusUpload(TusServer $server, Response $response): bool
    {
        $location = $response->headers->get('location', '');
        $tusKey = basename($location);
        if ($tusKey === '') {
            $this->logService->log(
                'upload',
                'create',
                null,
                LogLevel::ERROR,
                'Failed to get TUS key',
                ['location' => $location]
            );

            return false;
        }

        $fileData = $server->getCache()->get($tusKey);
        if (!is_array($fileData)) {
            $this->logService->log(
                'upload',
                'create',
                null,
                LogLevel::ERROR,
                'No file data found in TUS cache',
                ['tus' => $tusKey]
            );

            return false;
        }

        /** @var UserEntity $userEntity */
        $userEntity = $this->getUser();
        $user = UserMapper::toDomain($userEntity);

        $tariff = $user->tariff();
        if ($tariff === null) {
            $this->logService->log(
                'upload',
                'create',
                null,
                LogLevel::ERROR,
                'User without tariff',
                ['tus' => $tusKey]
            );

            throw new RuntimeException('User has no tariff');
        }

        $maxFileSize = $tariff->videoSize()->value() * 1024 * 1024;
        if ($fileData['size'] > $maxFileSize) {
            $this->flashRealtimeNotifier->notify(
                $user->id(),
                $this->flashNotificationFactory->uploadFailed(null, 'File size exceeds '.$tariff->videoSize()->value().' MB')
            );

            return false;
        }

        $storageCapacityBytes = (int)($tariff->storageGb()->value() * 1024 * 1024 * 1024);
        $usedBytes = $this->storageRepository->getUsedStorageSize($user->id());
        if ($usedBytes > $storageCapacityBytes) {
            $this->flashRealtimeNotifier->notify(
                $user->id(),
                $this->flashNotificationFactory->uploadFailed(null, 'Storage is full.')
            );

            return false;
        }

        $video = $this->videoFactory->fromFilename($fileData['name'], $user->id());
        $video->updateMeta([
            'size' => $fileData['size'],
            'tus' => $tusKey,
        ]);
        $video = $this->videoRepository->save($video);

        $fileData['videoId'] = $video->id()->toRfc4122();
        $server->getCache()->set($tusKey, $fileData);

        return true;
    }
}
