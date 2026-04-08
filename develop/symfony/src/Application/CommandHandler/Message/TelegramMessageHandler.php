<?php
declare(strict_types=1);

namespace App\Application\CommandHandler\Message;

use App\Application\Command\Message\TelegramMessage;
use App\Application\Logging\LogServiceInterface;
use App\Tests\Application\CommandHandler\Message\TelegramMessageHandlerTest;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.command')]
final readonly class TelegramMessageHandler
{
    private const int RATE_LIMIT_MAX = 10; // Max consecutive deferred messages before flood
    private const int RATE_LIMIT_SKIP_TIME = 300; // Skip messages for 5 minutes in flood mode
    private const float MIN_INTERVAL = 1.0; // Minimum 1 second between messages

    public function __construct(
        #[Autowire('%env(TELEGRAM_TOKEN)%')]
        private string $token,
        private LogServiceInterface $logService,
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function __invoke(TelegramMessage $command): void
    {
        if (empty($this->token)) {
            throw new \LogicException('Telegram message token cannot be empty.');
        }

        $chatId = $command->chatId;
        $floodedKey = "telegram_flooded_{$chatId}";
        $lastTimeKey = "telegram_last_time_{$chatId}";
        $deferredCountKey = "telegram_deferred_count_{$chatId}";

        if ($this->cache->hasItem($floodedKey)) {
            $this->logService->log('telegram', 'message', null, LogLevel::WARNING, "Channel {$chatId} is flooded, skipping message");
            return;
        }

        $lastTimeItem = $this->cache->getItem($lastTimeKey);
        $lastTime = $lastTimeItem->get() ?: 0;
        $now = microtime(true);
        $timeSinceLastSend = $now - $lastTime;

        $deferredCountItem = $this->cache->getItem($deferredCountKey);
        if ($timeSinceLastSend > self::MIN_INTERVAL) {
            $deferredCountItem->set(0);
            $deferredCountItem->expiresAfter(60);
            $this->cache->save($deferredCountItem);

            $lastTimeItem->set(microtime(true));
            $lastTimeItem->expiresAfter(3600);
            $this->cache->save($lastTimeItem);

            $this->sendTelegramMessage($chatId, $command->text, $command->silent);
            $this->logService->log('telegram', 'message', null, LogLevel::DEBUG, "Normal message sent to channel {$chatId}");
        } else {
            $deferredCount = $deferredCountItem->get() ?: 0;
            if ($deferredCount < self::RATE_LIMIT_MAX) {
                $deferredCount++;
                $deferredCountItem->set($deferredCount);
                $deferredCountItem->expiresAfter(60);
                $this->cache->save($deferredCountItem);

                $sleepTime = (self::MIN_INTERVAL - $timeSinceLastSend) * 1_000_000;
                usleep((int)$sleepTime);

                $lastTimeItem->set(microtime(true));
                $lastTimeItem->expiresAfter(3600);
                $this->cache->save($lastTimeItem);

                $this->sendTelegramMessage($chatId, $command->text, $command->silent);
                $this->logService->log('telegram', 'message', null, LogLevel::DEBUG, "Channel {$chatId} increase deferred messages count to {$deferredCount}");
            } else {
                $this->sendTelegramMessage($chatId, '<b>⚠️ Channel Flooded</b>\nToo many messages, skipping next for some time.', true);

                $floodedItem = $this->cache->getItem($floodedKey);
                $floodedItem->set(true);
                $floodedItem->expiresAfter(self::RATE_LIMIT_SKIP_TIME);
                $this->cache->save($floodedItem);

                $this->logService->log('telegram', 'message', null, LogLevel::WARNING, "Channel {$chatId} flooded after {$deferredCount} deferred messages");
            }
        }
    }

    private function sendTelegramMessage(int $chatId, string $text, bool $silent): void
    {
        if (in_array($chatId, TelegramMessageHandlerTest::TEST_CHAT_IDS)) {
            return;
        }

        $data = array(
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'html',
            'disable_web_page_preview' => true,
            'disable_notification' => $silent
        );

        $ch = curl_init("https://api.telegram.org/bot{$this->token}/sendMessage");

        $curlOptions = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_TIMEOUT => 10,
        );

        $proxy = getenv('http_proxy') ?: getenv('https_proxy');
        if (!empty($proxy)) {
            $curlOptions[CURLOPT_PROXY] = $proxy;
            $curlOptions[CURLOPT_PROXYTYPE] = CURLPROXY_HTTP;
        }

        curl_setopt_array($ch, $curlOptions);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->logService->log('telegram', 'message', null, LogLevel::ERROR, $error);
        }

        $this->logService->log('telegram', 'message', null, LogLevel::DEBUG, 'Response', json_decode($response, true));
    }
}
