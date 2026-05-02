<?php
declare(strict_types=1);

namespace App\Infrastructure\Upload\EventListener;

use App\Application\Command\Video\VideoUploaded;
use App\Application\Factory\VideoFactory;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use TusPhp\Events\UploadComplete;
use TusPhp\Tus\Server as TusServer;

/**
 * Fires when a TUS upload finishes.
 *
 * Looks up the Video entity that was pre-created by UploadController (loading=true)
 * via the videoId stored in the TUS file cache, then dispatches VideoUploaded to
 * transition it to loading=false (quota check → S3 → markLoaded).
 */
readonly class TusPostFinishListener
{
    public function __construct(
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
        private Security $security,
        private VideoRepositoryInterface $videoRepository,
        private VideoFactory $videoFactory,
        private TusServer $server,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    #[AsEventListener(event: UploadComplete::NAME)]
    public function __invoke(UploadComplete $event): void
    {
        $file = $event->getFile();
        $details = $file->details();

        // Allow re-upload of the same file
        $this->server->getCache()->delete($file->getKey());

        $videoIdStr = $details['videoId'] ?? null;
        if ($videoIdStr === null) {
            throw new \RuntimeException('Video ID not found');
        }

        $video = $this->videoRepository->findById(Uuid::fromString($videoIdStr));
        if ($video === null) {
            throw new \RuntimeException('Video not found');
        }

        $this->commandBus->dispatch(new VideoUploaded(
            video: $video,
            filePath: $file->getFilePath(),
            filename: $details['metadata']['originalName'] ?? $file->getName(),
        ));
    }
}
