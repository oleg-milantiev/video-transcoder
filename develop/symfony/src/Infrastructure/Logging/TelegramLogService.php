<?php
declare(strict_types=1);

namespace App\Infrastructure\Logging;

use App\Application\Command\Message\TelegramMessage;
use App\Application\DTO\TelegramMessageDTO;
use App\Application\Logging\LogServiceInterface;
use App\Domain\Shared\ValueObject\Uuid;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;

final readonly class TelegramLogService implements LogServiceInterface
{
    private const array ROUTE = [
        'user' => [
            'create' => [
                'template' => "📹 {{ text }}\nE-mail: {{ email }}\nTariff: {{ tariff }}",
                'userIds' => [1460996390],
            ],
        ],
        'video' => [
            'create' => [
                'template' => <<< TWIG
✅ Video created: <a href="{{ url('video_details', {uuid: video.uuid}) }}">{{ video.title }}</a>
By {{ user.email }}
TWIG,
                'userIds' => [1460996390],
            ]
        ],
    ];

    private const TEMPLATE_ERROR = <<<TWIG
{{ name }}:{{ action }}:{{ uuid }} ({{ level }})
{{ text }}
TWIG;

    public function __construct(
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
        private LogServiceInterface $logService,
        private Environment $twig,
    ) {
    }

    public function log(string $name, string $action, ?Uuid $objectId, string $level, string $text, array $context = []): void
    {
        // todo rate limit
        $template = null;

        if (in_array($level, [LogLevel::CRITICAL, LogLevel::ERROR, LogLevel::EMERGENCY], true) &&
            $name !== 'telegram' &&
            $action !== 'log'
        ) {
            $template = self::TEMPLATE_ERROR;
        }

        if (isset(self::ROUTE[$name][$action])) {
            $template = self::ROUTE[$name][$action]['template'];
        }

        if ($template !== null) {
            $context['name'] = $name;
            $context['action'] = $action;
            $context['uuid'] = $objectId?->toRfc4122();
            $context['level'] = $level;
            $context['text'] = $text;

            try {
                $renderedText = $this->twig->createTemplate($template)->render($context);
                $this->send(self::ROUTE[$name][$action]['userIds'], $renderedText);
            } catch (LoaderError|SyntaxError $e) {
                $context['message'] = $e->getMessage();
                $this->logService->log('telegram', 'log', null, LogLevel::CRITICAL, 'Error render twig template', $context);
            }
        }
    }

    private function send(array $userIds, string $text): void
    {
        try {
            foreach ($userIds as $userId) {
                $this->commandBus->dispatch(
                    new TelegramMessage(new TelegramMessageDTO(
                        chatId: $userId,
                        text: $text,
                        silent: !(LogLevel::ERROR || LogLevel::CRITICAL || LogLevel::EMERGENCY),
                    ))
                );
            }
        } catch (ExceptionInterface $e) {
            $this->logService->log('telegram', 'log', null, LogLevel::CRITICAL, 'Error dispatch message', [
                'message' => $e->getMessage(),
                'text' => $text,
            ]);
        }
    }
}
