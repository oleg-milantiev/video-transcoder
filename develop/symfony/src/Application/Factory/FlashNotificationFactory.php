<?php
declare(strict_types=1);

namespace App\Application\Factory;

use App\Application\DTO\FlashNotificationDTO;
use App\Domain\Video\Entity\Task;
use App\Domain\Video\Entity\Video;
use App\Domain\Video\ValueObject\RealtimeNotification;
use App\Domain\Video\ValueObject\RealtimeNotificationLevel;

final readonly class FlashNotificationFactory
{
    public function uploadCompleted(Video $video): FlashNotificationDTO
    {
        $videoId = $video->id()?->toRfc4122() ?? '';

        $notification = RealtimeNotification::create(
            level: RealtimeNotificationLevel::SUCCESS,
            title: 'Upload completed',
            html: sprintf('Video was uploaded. <a href="/video/%s">Open details</a>.', $videoId),
            timerMs: 7000,
        );

        return FlashNotificationDTO::fromDomain($notification);
    }

    public function uploadFailed(?Video $video, string $message): FlashNotificationDTO
    {
        $videoId = $video?->id()?->toRfc4122() ?? '';

        $notification = RealtimeNotification::create(
            level: RealtimeNotificationLevel::ERROR,
            title: 'Upload failed',
            html: 'Video upload failed.'. ($videoId ? ' <a href="/video/'. $videoId .'">Open details</a>' : '') .'<br>'. $message,
            timerMs: 7000,
        );

        return FlashNotificationDTO::fromDomain($notification);
    }

    public function transcodeStarted(Task $task): FlashNotificationDTO
    {
        $videoId = $task->videoId()->toRfc4122();

        $notification = RealtimeNotification::create(
            level: RealtimeNotificationLevel::INFO,
            title: 'Transcoding started',
            html: sprintf('Task is running. <a href="/video/%s">Watch progress</a>.', $videoId),
            timerMs: 5000,
        );

        return FlashNotificationDTO::fromDomain($notification);
    }

    public function transcodeCompleted(Task $task): FlashNotificationDTO
    {
        $videoId = $task->videoId()->toRfc4122();
        $taskId = $task->id()?->toRfc4122() ?? '';

        $notification = RealtimeNotification::create(
            level: RealtimeNotificationLevel::SUCCESS,
            title: 'Transcoding completed',
            html: sprintf(
                'Task finished successfully. <a href="/task/%s/download" download="">Download result</a> or <a href="/video/%s">open video</a>.',
                $taskId,
                $videoId,
            ),
            timerMs: 10000,
        );

        return FlashNotificationDTO::fromDomain($notification);
    }

    public function transcodeFailed(Task $task, \Throwable $exception): FlashNotificationDTO
    {
        $videoId = $task->videoId()->toRfc4122();
        $safeMessage = htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $notification = RealtimeNotification::create(
            level: RealtimeNotificationLevel::ERROR,
            title: 'Transcoding failed',
            html: sprintf(
                '%s <a href="/video/%s">Open video details</a>.',
                $safeMessage,
                $videoId,
            ),
            timerMs: 12000,
        );

        return FlashNotificationDTO::fromDomain($notification);
    }
}
