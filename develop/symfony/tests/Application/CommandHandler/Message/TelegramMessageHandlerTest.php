<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler\Message;

use App\Application\Command\Message\TelegramMessage;
use App\Application\CommandHandler\Message\TelegramMessageHandler;
use App\Application\Logging\LogServiceInterface;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class TelegramMessageHandlerTest extends TestCase
{
    private const string TELEGRAM_TOKEN = 'test_token_12345';

    private CacheItemPoolInterface $cache;
    private LogServiceInterface $logService;
    private TelegramMessageHandler $handler;

    protected function setUp(): void
    {
        $this->cache = new ArrayAdapter();
        $this->logService = $this->createMock(LogServiceInterface::class);
        $this->handler = new TelegramMessageHandler(self::TELEGRAM_TOKEN, $this->logService, $this->cache);
    }

    public function testThrowsExceptionWhenTokenIsEmpty(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Telegram message token cannot be empty.');

        // log must never be called because exception is thrown before any logic
        $this->logService->expects($this->never())->method('log');

        $handler = new TelegramMessageHandler('', $this->logService, $this->cache);
        $command = new TelegramMessage(TelegramMessageHandler::TEST_CHAT_IDS[0], 'Test message', false);
        ($handler)($command);
    }

    public function testChannelNotFloodedWhenCacheEmpty(): void
    {
        $command = new TelegramMessage(TelegramMessageHandler::TEST_CHAT_IDS[0], 'Test message', false);

        // Cache is empty, so no flood marker should exist
        $floodKey = "telegram_flooded_" . TelegramMessageHandler::TEST_CHAT_IDS[0];
        $this->assertFalse($this->cache->hasItem($floodKey));

        // Should not throw, and should try to log
        $this->logService->expects($this->atLeastOnce())
            ->method('log');

        ($this->handler)($command);
    }

    public function testSkipsMessageWhenChannelIsFlooded(): void
    {
        $command = new TelegramMessage(TelegramMessageHandler::TEST_CHAT_IDS[0], 'Test message', false);

        // Mark channel as flooded
        $floodKey = "telegram_flooded_" . TelegramMessageHandler::TEST_CHAT_IDS[0];
        $floodedItem = $this->cache->getItem($floodKey);
        $floodedItem->set(true);
        $floodedItem->expiresAfter(300);
        $this->cache->save($floodedItem);

        $this->logService->expects($this->once())
            ->method('log')
            ->with('telegram', 'message', null, LogLevel::WARNING, "Channel " . TelegramMessageHandler::TEST_CHAT_IDS[0] . " is flooded, skipping message");

        ($this->handler)($command);
    }

    public function testDeferredCountTracking(): void
    {
        $chatId = TelegramMessageHandler::TEST_CHAT_IDS[2];
        $command = new TelegramMessage($chatId, 'Test message', false);

        // Set recent last time to trigger deferral
        $lastTimeKey = "telegram_last_time_" . $chatId;
        $lastTimeItem = $this->cache->getItem($lastTimeKey);
        $lastTimeItem->set(microtime(true) - 0.1); // Very recent — forces deferral path
        $this->cache->save($lastTimeItem);

        // deferred path still calls sendTelegramMessage + logs the deferred count
        $this->logService->expects($this->atLeastOnce())->method('log');

        ($this->handler)($command);

        // Check deferred count was incremented
        $deferredCountKey = "telegram_deferred_count_" . $chatId;
        $deferredItem = $this->cache->getItem($deferredCountKey);
        $this->assertGreaterThan(0, $deferredItem->get());
    }

    public function testMultipleChatIdsAreHandledIndependently(): void
    {
        $chatId1 = TelegramMessageHandler::TEST_CHAT_IDS[3];
        $chatId2 = TelegramMessageHandler::TEST_CHAT_IDS[4];

        // Set past time for both to allow sending
        $lastTimeItem1 = $this->cache->getItem("telegram_last_time_" . $chatId1);
        $lastTimeItem1->set(microtime(true) - 2.0);
        $this->cache->save($lastTimeItem1);

        $lastTimeItem2 = $this->cache->getItem("telegram_last_time_" . $chatId2);
        $lastTimeItem2->set(microtime(true) - 2.0);
        $this->cache->save($lastTimeItem2);

        $command1 = new TelegramMessage($chatId1, 'Message 1', false);
        $command2 = new TelegramMessage($chatId2, 'Message 2', false);

        $this->logService->expects($this->atLeastOnce())
            ->method('log');

        ($this->handler)($command1);
        ($this->handler)($command2);

        // Verify separate cache entries exist
        $this->assertTrue($this->cache->hasItem("telegram_last_time_" . $chatId1));
        $this->assertTrue($this->cache->hasItem("telegram_last_time_" . $chatId2));
    }

    public function testDeferredCountResetsWhenMessageSent(): void
    {
        $chatId = 555555555;

        // Set a deferred count
        $deferredKey = "telegram_deferred_count_" . $chatId;
        $deferredItem = $this->cache->getItem($deferredKey);
        $deferredItem->set(5);
        $this->cache->save($deferredItem);

        // Set past time to allow sending
        $lastTimeKey = "telegram_last_time_" . $chatId;
        $lastTimeItem = $this->cache->getItem($lastTimeKey);
        $lastTimeItem->set(microtime(true) - 2.0);
        $this->cache->save($lastTimeItem);

        $command = new TelegramMessage($chatId, 'Test message', false);

        $this->logService->expects($this->atLeastOnce())
            ->method('log');

        ($this->handler)($command);

        // Deferred count should be reset to 0
        $deferredCheckItem = $this->cache->getItem($deferredKey);
        $this->assertSame(0, $deferredCheckItem->get());
    }

    public function testSilentMessageIsProcessed(): void
    {
        $command = new TelegramMessage(TelegramMessageHandler::TEST_CHAT_IDS[0], 'Silent message', true);

        // Set past time to allow sending
        $lastTimeItem = $this->cache->getItem("telegram_last_time_" . TelegramMessageHandler::TEST_CHAT_IDS[0]);
        $lastTimeItem->set(microtime(true) - 2.0);
        $this->cache->save($lastTimeItem);

        $this->logService->expects($this->atLeastOnce())
            ->method('log');

        ($this->handler)($command);
    }

    public function testMessageWithEmptyText(): void
    {
        $command = new TelegramMessage(TelegramMessageHandler::TEST_CHAT_IDS[0], '', false);

        // Set past time to allow sending
        $lastTimeItem = $this->cache->getItem("telegram_last_time_" . TelegramMessageHandler::TEST_CHAT_IDS[0]);
        $lastTimeItem->set(microtime(true) - 2.0);
        $this->cache->save($lastTimeItem);

        $this->logService->expects($this->atLeastOnce())
            ->method('log');

        ($this->handler)($command);
    }

    public function testNegativeChatId(): void
    {
        $chatId = TelegramMessageHandler::TEST_CHAT_IDS[1]; // Private chat
        $command = new TelegramMessage($chatId, 'Test message', false);

        // Set past time to allow sending
        $lastTimeItem = $this->cache->getItem("telegram_last_time_" . $chatId);
        $lastTimeItem->set(microtime(true) - 2.0);
        $this->cache->save($lastTimeItem);

        $this->logService->expects($this->atLeastOnce())
            ->method('log');

        ($this->handler)($command);
    }
}
