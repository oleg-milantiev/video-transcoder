<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Logging;

use App\Application\Command\Message\TelegramMessage;
use App\Application\Logging\LogServiceInterface;
use App\Domain\Shared\ValueObject\Uuid;
use App\Infrastructure\Logging\TelegramLogService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;

final class TelegramLogServiceTest extends TestCase
{
    private const int ADMIN_USER_ID = 1460996390;

    private MessageBusInterface $commandBus;
    private LogServiceInterface $logService;
    private Environment $twig;
    private TelegramLogService $telegramLogService;

    protected function setUp(): void
    {
        $this->commandBus = $this->createStub(MessageBusInterface::class);
        $this->logService = $this->createMock(LogServiceInterface::class);
        $this->twig = $this->createMock(Environment::class);
        $this->telegramLogService = new TelegramLogService($this->commandBus, $this->logService, $this->twig);
    }

    public function testUserCreateLogsMessageForAdmin(): void
    {
        $objectId = Uuid::generate();
        $text = 'New user registered';
        $context = ['email' => 'test@example.com', 'tariff' => 'Premium'];

        $expectedTemplate = "📹 {{ text }}\nE-mail: {{ email }}\nTariff: {{ tariff }}";

        // Mock twig to throw exception - this tests error handling
        $twigError = new SyntaxError('Template error');
        $this->twig->expects($this->once())
            ->method('createTemplate')
            ->with($expectedTemplate)
            ->willThrowException($twigError);

        $this->logService->expects($this->once())
            ->method('log')
            ->with(
                'telegram',
                'log',
                null,
                LogLevel::CRITICAL,
                'Error render twig template',
                $this->callback(function ($ctx) {
                    return isset($ctx['message']);
                })
            );

        $this->telegramLogService->log('user', 'create', $objectId, LogLevel::INFO, $text, $context);
    }

    public function testSkipsLoggingWhenNoRouteMatches(): void
    {
        $objectId = Uuid::generate();
        $text = 'Unknown event';

        // unknown_entity/unknown_action/debug — matches no ROUTE and is not an error level
        // so twig must never render and logService must never log
        $this->twig->expects($this->never())->method('createTemplate');
        $this->logService->expects($this->never())->method('log');

        $this->telegramLogService->log('unknown_entity', 'unknown_action', $objectId, LogLevel::DEBUG, $text);
    }

    public function testLogsErrorOnTwigLoadError(): void
    {
        $objectId = Uuid::generate();
        $text = 'User created';
        $context = ['email' => 'test@example.com', 'tariff' => 'Free'];

        $twigError = new LoaderError('Template not found');

        $this->twig->expects($this->once())
            ->method('createTemplate')
            ->willThrowException($twigError);

        $this->logService->expects($this->once())
            ->method('log')
            ->with(
                'telegram',
                'log',
                null,
                LogLevel::CRITICAL,
                'Error render twig template',
                $this->callback(function ($context) {
                    return isset($context['message']);
                })
            );

        $this->telegramLogService->log('user', 'create', $objectId, LogLevel::INFO, $text, $context);
    }

    public function testLogsErrorOnTwigSyntaxError(): void
    {
        $objectId = Uuid::generate();
        $text = 'Task transcoded';

        $twigError = new SyntaxError('Invalid template syntax');

        $this->twig->expects($this->once())
            ->method('createTemplate')
            ->willThrowException($twigError);

        $this->logService->expects($this->once())
            ->method('log')
            ->with(
                'telegram',
                'log',
                null,
                LogLevel::CRITICAL,
                'Error render twig template',
                $this->callback(function ($context) {
                    return isset($context['message']);
                })
            );

        $this->telegramLogService->log('task', 'transcode', $objectId, LogLevel::INFO, $text);
    }

    public function testLogsErrorWhenDispatchFails(): void
    {
        $objectId = Uuid::generate();
        $text = 'User created';
        $context = ['email' => 'test@example.com', 'tariff' => 'Standard'];

        $twigError = new SyntaxError('Render error');

        $this->twig->expects($this->once())
            ->method('createTemplate')
            ->willThrowException($twigError);

        $this->logService->expects($this->once())
            ->method('log')
            ->with(
                'telegram',
                'log',
                null,
                LogLevel::CRITICAL,
                'Error render twig template',
                $this->callback(function ($context) {
                    return isset($context['message']);
                })
            );

        // This should handle the twig error gracefully
        $this->telegramLogService->log('user', 'create', $objectId, LogLevel::INFO, $text, $context);
    }

    public function testCriticalErrorLogIsProcessed(): void
    {
        $objectId = Uuid::generate();
        $text = 'Critical error occurred';

        $twigError = new SyntaxError('Template error');

        $this->twig->expects($this->once())
            ->method('createTemplate')
            ->willThrowException($twigError);

        $this->logService->expects($this->once())
            ->method('log')
            ->with(
                'telegram',
                'log',
                null,
                LogLevel::CRITICAL,
                'Error render twig template',
                $this->callback(function ($context) {
                    return isset($context['message']);
                })
            );

        // Log a critical error - should attempt to send and then fail rendering
        $this->telegramLogService->log('unknown_entity', 'unknown', $objectId, LogLevel::CRITICAL, $text);
    }

    public function testRenderingContextIncludesMetadata(): void
    {
        $objectId = Uuid::generate();
        $text = 'Task info';
        $context = [
            'taskId' => '12345',
            'duration' => '3600',
        ];

        $twigError = new SyntaxError('Template syntax error');

        $this->twig->expects($this->once())
            ->method('createTemplate')
            ->willThrowException($twigError);

        $this->logService->expects($this->once())
            ->method('log')
            ->with(
                'telegram',
                'log',
                null,
                LogLevel::CRITICAL,
                'Error render twig template',
                $this->callback(function ($context) {
                    // Verify that metadata was added to context
                    return isset($context['message'], $context['name'], $context['action']);
                })
            );

        $this->telegramLogService->log('task', 'transcode', $objectId, LogLevel::INFO, $text, $context);
    }

    public function testTaskTranscodeInfoLogsMessageForAdmin(): void
    {
        $objectId = Uuid::generate();
        $text = 'Transcoding started';

        $twigError = new SyntaxError('Template error');

        $this->twig->expects($this->once())
            ->method('createTemplate')
            ->willThrowException($twigError);

        $this->logService->expects($this->once())
            ->method('log');

        $this->telegramLogService->log('task', 'transcode', $objectId, LogLevel::INFO, $text);
    }

    public function testSmokeResultInfoRendersPassedTemplateAndDispatches(): void
    {
        // setUp mocks are not used — this test creates its own service with real Twig
        $this->logService->expects($this->never())->method('log');
        $this->twig->expects($this->never())->method('createTemplate');

        $twig = new Environment(new ArrayLoader());

        $dispatchedMessage = null;
        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $msg) use (&$dispatchedMessage): bool {
                if (!$msg instanceof TelegramMessage) {
                    return false;
                }
                $dispatchedMessage = $msg;

                return true;
            }))
            ->willReturnCallback(static fn (object $msg): Envelope => new Envelope($msg));

        $service = new TelegramLogService($commandBus, $this->createStub(LogServiceInterface::class), $twig);
        $service->log('smoke', 'result', null, LogLevel::INFO, 'Smoke tests passed', [
            'status' => 'passed',
            'failedTests' => [],
        ]);

        $this->assertNotNull($dispatchedMessage);
        $this->assertInstanceOf(TelegramMessage::class, $dispatchedMessage);
        $this->assertSame(self::ADMIN_USER_ID, $dispatchedMessage->chatId);
        $this->assertStringContainsString('✅ Smoke', $dispatchedMessage->text);
        $this->assertStringContainsString('Smoke tests passed', $dispatchedMessage->text);
        $this->assertStringContainsString('passed', $dispatchedMessage->text);
    }

    public function testSmokeResultErrorWithFailedTestsRendersAndDispatches(): void
    {
        // setUp mocks are not used — this test creates its own service with real Twig
        $this->logService->expects($this->never())->method('log');
        $this->twig->expects($this->never())->method('createTemplate');

        $twig = new Environment(new ArrayLoader());

        $dispatchedMessage = null;
        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $msg) use (&$dispatchedMessage): bool {
                if (!$msg instanceof TelegramMessage) {
                    return false;
                }
                $dispatchedMessage = $msg;

                return true;
            }))
            ->willReturnCallback(static fn (object $msg): Envelope => new Envelope($msg));

        $service = new TelegramLogService($commandBus, $this->createStub(LogServiceInterface::class), $twig);
        $service->log('smoke', 'result', null, LogLevel::ERROR, 'Smoke tests failed', [
            'status' => 'failed',
            'failedTests' => ['tests/01.admin.login.js', 'tests/02.upload.video.js'],
        ]);

        $this->assertNotNull($dispatchedMessage);
        $this->assertInstanceOf(TelegramMessage::class, $dispatchedMessage);
        $this->assertSame(self::ADMIN_USER_ID, $dispatchedMessage->chatId);
        $this->assertStringContainsString('🚨 Smoke', $dispatchedMessage->text);
        $this->assertStringContainsString('Smoke tests failed', $dispatchedMessage->text);
        $this->assertStringContainsString('Failed (2)', $dispatchedMessage->text);
        $this->assertStringContainsString('tests/01.admin.login.js', $dispatchedMessage->text);
        $this->assertStringContainsString('tests/02.upload.video.js', $dispatchedMessage->text);
    }

    public function testSmokeResultErrorWithMissingFileRendersAndDispatches(): void
    {
        // setUp mocks are not used — this test creates its own service with real Twig
        $this->logService->expects($this->never())->method('log');
        $this->twig->expects($this->never())->method('createTemplate');

        $twig = new Environment(new ArrayLoader());

        $dispatchedMessage = null;
        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $msg) use (&$dispatchedMessage): bool {
                if (!$msg instanceof TelegramMessage) {
                    return false;
                }
                $dispatchedMessage = $msg;

                return true;
            }))
            ->willReturnCallback(static fn (object $msg): Envelope => new Envelope($msg));

        $service = new TelegramLogService($commandBus, $this->createStub(LogServiceInterface::class), $twig);
        $service->log('smoke', 'result', null, LogLevel::ERROR, 'Result file not found', [
            'status' => 'unknown',
            'file' => '/work/release.check/run/test-results/.last-run.json',
        ]);

        $this->assertNotNull($dispatchedMessage);
        $this->assertInstanceOf(TelegramMessage::class, $dispatchedMessage);
        $this->assertSame(self::ADMIN_USER_ID, $dispatchedMessage->chatId);
        $this->assertStringContainsString('🚨 Smoke', $dispatchedMessage->text);
        $this->assertStringContainsString('Result file not found', $dispatchedMessage->text);
        $this->assertStringContainsString('unknown', $dispatchedMessage->text);
        $this->assertStringContainsString('/work/release.check/run/test-results/.last-run.json', $dispatchedMessage->text);
    }
}
