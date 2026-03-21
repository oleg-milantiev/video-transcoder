<?php

namespace App\Application\CommandHandler\Video;

use App\Application\Command\Video\CreateVideo;
use App\Application\Command\Video\ExtractVideoMetadata;
use App\Application\Event\CreateVideoFail;
use App\Application\Event\CreateVideoStart;
use App\Application\Event\CreateVideoSuccess;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\Repository\VideoRepositoryInterface;
use App\Domain\Video\Service\Storage\StorageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\HttpFoundation\File\File;

#[AsMessageHandler(bus: 'messenger.bus.command')]
final readonly class CreateVideoHandler
{
    public function __construct(
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
        #[Autowire(service: 'messenger.bus.event')]
        private MessageBusInterface $eventBus,
        private VideoRepositoryInterface $videoRepository,
        private StorageInterface $storage,
    ) {
    }

    public function __invoke(CreateVideo $command): void
    {
        try {
            $this->eventBus->dispatch(new CreateVideoStart(
                userId: $command->userId(),
                filename: $command->file()->getName(),
            ));

            $video = Video::createFromCommand($command);
            $video = $this->videoRepository->save($video);

            $this->storage->upload(
                new File($command->file()->getFilePath()),
                $video->getSrcFilename(),
            );

            $this->videoRepository->log($video->id(), 'info', 'Video created', [
                'video' => print_r($video, true),
                'file' => $command->file()->details(),
            ]);

            $this->commandBus->dispatch(new ExtractVideoMetadata($video));
            $this->eventBus->dispatch(new CreateVideoSuccess(
                videoId: $video->id()?->toRfc4122(),
                userId: $command->userId(),
            ));
        } catch (\Exception $e) {
            $this->eventBus->dispatch(new CreateVideoFail(
                error: $e->getMessage(),
                userId: $command->userId(),
                filename: $command->file()->getName(),
            ));
        }
    }
}
