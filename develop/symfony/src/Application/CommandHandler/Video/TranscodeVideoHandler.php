<?php

namespace App\Application\CommandHandler\Video;

use App\Application\Command\Video\TranscodeVideo;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class TranscodeVideoHandler
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(TranscodeVideo $command): void
    {
        // TODO: implement actual transcoding logic
        $task = $command->scheduledTask;
        $this->logger->info('TranscodeVideoHandler received task', [
            'taskId' => $task->taskId,
            'userId' => $task->userId,
            'videoId' => $task->videoId,
        ]);
    }
}

