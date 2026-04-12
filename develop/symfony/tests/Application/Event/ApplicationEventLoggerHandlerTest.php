<?php

declare(strict_types=1);

namespace App\Tests\Application\Event;

use App\Application\Event\ApplicationEvent;
use App\Application\Event\ApplicationEventLoggerHandler;
use App\Application\Event\CreateVideoFail;
use App\Application\Event\CreateVideoStart;
use App\Application\Event\CreateVideoSuccess;
use App\Application\Event\TranscodeVideoFail;
use App\Application\Event\TranscodeVideoStart;
use App\Application\Event\TranscodeVideoSuccess;
use App\Application\Event\PatchVideoFail;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ApplicationEventLoggerHandlerTest extends TestCase
{
    public function testLogsErrorForFailEvents(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with('Application event dispatched', $this->isArray());

        $handler = new ApplicationEventLoggerHandler($logger);
        $handler(new CreateVideoFail('something went wrong', 'user-1', 'file.mp4'));
    }

    public function testLogsInfoForStartEvents(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('Application event dispatched', $this->isArray());

        $handler = new ApplicationEventLoggerHandler($logger);
        $handler(new CreateVideoStart('user-1', 'file.mp4'));
    }

    public function testLogsInfoForSuccessEvents(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('Application event dispatched', $this->isArray());

        $handler = new ApplicationEventLoggerHandler($logger);
        $handler(new CreateVideoSuccess('vid-1', 'user-1'));
    }

    public function testLogsInfoForTranscodeVideoFailEvents(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $handler = new ApplicationEventLoggerHandler($logger);
        $handler(new TranscodeVideoFail('ffmpeg crashed', 'task-1', 'vid-1'));
    }

    public function testLogsInfoForTranscodeVideoStartEvents(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info');

        $handler = new ApplicationEventLoggerHandler($logger);
        $handler(new TranscodeVideoStart('task-1', 'user-1', 'vid-1'));
    }

    public function testLogsInfoForTranscodeVideoSuccessEvents(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info');

        $handler = new ApplicationEventLoggerHandler($logger);
        $handler(new TranscodeVideoSuccess('task-1', 'vid-1'));
    }

    public function testLogsInfoForPatchVideoFailEvents(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $handler = new ApplicationEventLoggerHandler($logger);
        $handler(new PatchVideoFail('title invalid', 'vid-1'));
    }

    public function testLogsInfoForUnknownEventType(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('Application event dispatched', $this->isArray());

        // Create an anonymous event class whose short name does NOT end with Fail, Start, or Success
        $event = new readonly class extends ApplicationEvent {};

        $handler = new ApplicationEventLoggerHandler($logger);
        $handler($event);
    }
}
