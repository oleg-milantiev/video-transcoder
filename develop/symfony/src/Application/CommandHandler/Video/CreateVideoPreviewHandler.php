<?php

namespace App\Application\CommandHandler\Video;

use App\Application\Command\Video\CreateVideoPreview;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateVideoPreviewHandler
{
    public function __invoke(CreateVideoPreview $command): void
    {
        // TODO: Реализовать генерацию превью (через FFMpeg и т.д.)
        // На данном этапе это заглушка, как просил пользователь.
        $videoId = $command->getVideoId();

        // Логика будет добавлена позже.
        // echo "Generating preview for video: " . $videoId->toString() . PHP_EOL;
    }
}
