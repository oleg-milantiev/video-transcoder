<?php
declare(strict_types=1);

namespace App\Application\CommandHandler\Message;

use App\Application\Command\Mercure\PublishMercureMessage;
use App\Application\Command\Message\TelegramMessage;
use App\Application\Logging\LogServiceInterface;
use App\Application\Service\Mercure\MercurePublisherInterface;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.command')]
final readonly class TelegramMessageHandler
{
    public function __construct(
        #[Autowire('%env(TELEGRAM_TOKEN)%')]
        private string $token,
        private LogServiceInterface $logService,
    ) {
    }

    public function __invoke(TelegramMessage $command): void
    {
        if (empty($this->token)) {
            throw new \LogicException('Telegram message token cannot be empty.');
        }

        $data = array(
            'chat_id' => $command->message->chatId,
            'text' => $command->message->text,
            'parse_mode' => 'html',
            'disable_web_page_preview' => true,
            'disable_notification' => $command->message->silent
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
