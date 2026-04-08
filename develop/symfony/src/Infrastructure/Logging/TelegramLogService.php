<?php
declare(strict_types=1);

namespace App\Infrastructure\Logging;

use App\Application\Command\Message\TelegramMessage;
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
    private const int ADMIN_USER_ID = 1460996390;

    private const array ROUTE = [
        'user' => [ // name
            'create' => [ // action
                'any' => [ // level
                    'template' => "📹 {{ text }}\nE-mail: {{ email }}\nTariff: {{ tariff }}",
                    'userIds' => [self::ADMIN_USER_ID],
                ],
            ],
        ],
        'task' => [
            'transcode' => [
                LogLevel::INFO => [
                    /*
                     * todo унифицировать и использовать контекст
                    $this->logService->log('task', 'transcode', $task->id(), LogLevel::INFO, 'Transcode requested', [
                        'taskId' => $task->id()?->toRfc4122(),
                        'videoId' => $video->id()?->toRfc4122(),
                        'presetId' => $preset->id()?->toRfc4122(),
                        'userId' => $user->id()?->toRfc4122(),
                        'isRestart' => $task->status()->name !== 'PENDING',
                    ]);

                    $this->logService->log('task', 'transcode', $task->id(), LogLevel::INFO, 'Transcoding started');

                    $this->logService->log('task', 'transcode', $task->id(), LogLevel::INFO, 'Task cancelled before ffmpeg start');

                    $this->logService->log('task', 'transcode', $task->id(), LogLevel::INFO, 'Transcoding finished successfully', [
                        'time' => microtime(true) - $context->timeStart,
                        'size' => $fileSize,
                    ]);
                    */
                    'template' => <<< TWIG
{{ text }} ({{ uuid }})
TWIG,
                    'userIds' => [self::ADMIN_USER_ID],
                ],
            ],
        ],
        'video' => [
            'create' => [
                'any' => [
                    'template' => <<< TWIG
✅ Video created: <a href="{{ url('video_details', {uuid: video.uuid}) }}">{{ video.title }}</a>
By {{ user.email }}
TWIG,
                    'userIds' => [self::ADMIN_USER_ID],
                ],
            ]
        ],
        'smoke' => [
            'result' => [
                LogLevel::INFO => [
                    'template' => <<< TWIG
✅ Smoke: {{ text }}
Status: {{ status }}
TWIG,
                    'userIds' => [self::ADMIN_USER_ID],
                ],
                LogLevel::ERROR => [
                    'template' => <<< TWIG
🚨 Smoke: {{ text }}
Status: {{ status }}{% if failedTests is defined and failedTests|length > 0 %}
Failed ({{ failedTests|length }}):{% for t in failedTests %}
  — {{ t }}{% endfor %}{% endif %}{% if file is defined %}
File: {{ file }}{% endif %}
TWIG,
                    'userIds' => [self::ADMIN_USER_ID],
                ],
            ],
        ],
    ];

    private const string TEMPLATE_ERROR = <<<TWIG
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
        $route = null;

        if (in_array($level, [LogLevel::CRITICAL, LogLevel::ERROR, LogLevel::EMERGENCY], true) &&
            $name !== 'telegram' &&
            $action !== 'log'
        ) {
            $route = [
                'template' => self::TEMPLATE_ERROR,
                'userIds' => [self::ADMIN_USER_ID],
            ];
        }

        if (isset(self::ROUTE[$name][$action]['any'])) {
            $route = self::ROUTE[$name][$action]['any'];
        } elseif (isset(self::ROUTE[$name][$action][$level])) {
            $route = self::ROUTE[$name][$action][$level];
        }

        if ($route === null) {
            return;
        }

        $context['name'] = $name;
        $context['action'] = $action;
        $context['uuid'] = $objectId?->toRfc4122();
        $context['level'] = $level;
        $context['text'] = $text;

        try {
            $renderedText = $this->twig->createTemplate($route['template'])->render($context);
            $this->send($route['userIds'], $renderedText);
        } catch (LoaderError|SyntaxError $e) {
            $context['message'] = $e->getMessage();
            $this->logService->log('telegram', 'log', null, LogLevel::CRITICAL, 'Error render twig template', $context);
        }
    }

    private function send(array $userIds, string $text): void
    {
        try {
            foreach ($userIds as $userId) {
                $this->commandBus->dispatch(
                    new TelegramMessage(
                        chatId: $userId,
                        text: $text,
                        silent: !(LogLevel::ERROR || LogLevel::CRITICAL || LogLevel::EMERGENCY),
                    )
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
