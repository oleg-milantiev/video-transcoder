# Backend Architecture (Symfony DDD)

Бэкенд проекта построен на принципах **Domain-Driven Design (DDD)** и разделён на четыре основных слоя: `Domain`, `Application`, `Infrastructure`, `Presentation`.

---

## 1. Domain Layer (`src/Domain`)

Содержит ядро бизнес-логики. Не зависит от фреймворков и внешних библиотек.

Domain/
├── Shared/
│   ├── Exception/InvalidUuidException.php
│   └── ValueObject/Uuid.php
├── User/
│   ├── Entity/User.php, Tariff.php, Payment.php
│   ├── Exception/UserNotFound.php, TariffNotFound.php, InvalidPaymentDates, PaymentNotFound
│   ├── Repository/UserRepositoryInterface.php, TariffRepositoryInterface.php, PaymentRepositoryInterface
│   └── ValueObject/ (User*, PasswordHash, Tariff*, Payment*)
└── Video/
    ├── DTO/
    ├── Entity/Video.php, Task.php, Preset.php
    ├── Event/
    ├── Exception/
    ├── Repository/ (VideoRepositoryInterface, TaskRepositoryInterface, PresetRepositoryInterface, StorageRepositoryInterface, PaginatedRepositoryInterface)
    ├── Service/
    └── ValueObject/ (16 files: VideoTitle, FileExtension, VideoDates, TaskDates, TaskStatus, Progress, AudioCodec, Codec, Format, PresetBitrate, PresetName, PresetTitle, RealtimeNotification, RealtimeNotificationLevel, RealtimeNotificationPosition, VideoCodec)

### Агрегаты и Сущности

| Агрегат | Класс | Описание |
|---------|-------|----------|
| `Video` | `Domain/Video/Entity/Video.php` | Загруженное видео; хранит title, extension, userId, meta (width/height/duration/size/preview/sourceKey), soft-delete флаг |
| `Task` | `Domain/Video/Entity/Task.php` | Задача транскодирования; статус (`TaskStatus`), прогресс, meta (height/width/bitrate/output/sizeExpected), soft-delete |
| `Preset` | `Domain/Video/Entity/Preset.php` | Пресет транскодирования; videoCodec, audioCodec, format, bitrate (карта height→Mbps) |
| `User` | `Domain/User/Entity/User.php` | Пользователь; email, роли, ссылка на Tariff |
| `Tariff` | `Domain/User/Entity/Tariff.php` | Тариф; delay, instance, videoDuration, videoSize, maxWidth, maxHeight, storageGb, storageHour |

### ValueObjects

**Video:** `Uuid`, `VideoTitle`, `FileExtension`, `TaskStatus`, `Progress`, `AudioCodec`, `VideoCodec`, `Format`, `PresetBitrate`, `PresetName`, `PresetTitle`, `RealtimeNotification`, `RealtimeNotificationLevel`, `RealtimeNotificationPosition`, `TaskDates`, `VideoDates`

**User:** `PasswordHash`, `UserEmail`, `UserRoles`, `UserCreatedAt`, `UserLoginedAt`, `TariffDelay`, `TariffInstance`, `TariffMaxHeight`, `TariffMaxWidth`, `TariffStorageGb`, `TariffStorageHour`, `TariffTitle`, `TariffVideoDuration`, `TariffVideoSize`

### Репозитории (интерфейсы)

`VideoRepositoryInterface`, `TaskRepositoryInterface`, `PresetRepositoryInterface`, `UserRepositoryInterface`, `TariffRepositoryInterface`

### Domain Events

`VideoCreated`, `VideoMetadataExtractionStarted/Finished`, `VideoPreviewGenerationStarted/Finished`

---

## 2. Application Layer (`src/Application`)

Координирует выполнение задач. Делегирует бизнес-логику доменным объектам.

### Commands & CommandHandlers

Обрабатываются через **Symfony Messenger** (bus `messenger.bus.command`).

| Command | Handler | Описание |
|---------|---------|----------|
| `CreateVideo` | `CreateVideoHandler` | Создаёт Video entity; диспатчит `ExtractVideoMetadata` и `CreateVideoPreview` |
| `ExtractVideoMetadata` | `ExtractVideoMetadataHandler` | Извлекает метаданные через ffprobe |
| `CreateVideoPreview` | `CreateVideoPreviewHandler` | Генерирует постер (превью) через ffmpeg |
| `CleanupDeletedVideoMedia` | `CleanupDeletedVideoMediaHandler` | Удаляет файлы soft-deleted видео из хранилища |
| `TranscodeVideo` | `TranscodeVideoHandler` | Запускает ffmpeg; mutex через Symfony Lock; проверяет квоту хранилища; использует `TranscodeTaskPreparationService`, `TranscodeProcessService`, `TranscodeTaskFinalizationService` |
| `StartTaskScheduler` | `StartTaskSchedulerHandler` | Читает очередь scheduled-задач и диспатчит `TranscodeVideo` для каждой |
| `PublishMercureMessage` | `PublishMercureMessageHandler` | Публикует сообщение в Mercure Hub |
| `TelegramMessage` | `TelegramMessageHandler` | Отправляет уведомление в Telegram |

### Queries & QueryHandlers

Реализация **CQRS** — read-side запросы.

| Query | Handler | Результат |
|-------|---------|-----------|
| `GetVideoListQuery` | `GetVideoListHandler` | `VideoListResponse` (пагинированный список `VideoItemDTO`) |
| `GetVideoDetailsQuery` | `GetVideoDetailsHandler` | `VideoDetailsDTO` (VideoItemDTO + PresetItemDTO[] + TaskItemDTO[]) |
| `PatchVideoQuery` | `PatchVideoHandler` | `VideoItemDTO`; обновляет title; нотифицирует через Mercure |
| `DeleteVideoQuery` | `DeleteVideoHandler` | void; soft-delete; диспатчит `CleanupDeletedVideoMedia` |
| `StartTranscodeQuery` | `StartTranscodeHandler` | `TaskItemDTO`; создаёт/рестартует задачу; диспатчит `StartTaskScheduler` |
| `GetTaskListQuery` | `GetTaskListHandler` | `TaskListResponse` (пагинированный список `TaskItemDTO`) |
| `TaskCancelQuery` | `TaskCancelHandler` | void; устанавливает флаг отмены через `TaskCancellationTrigger` |
| `TaskDownloadQuery` | `TaskDownloadHandler` | `string` (публичный URL файла результата) |
| `DeleteTaskQuery` | `DeleteTaskHandler` | void; soft-delete задачи |

Все query/command отправляются через `QueryBus` (`Application/QueryHandler/QueryBus.php`).

### DTOs

| DTO | Описание |
|-----|----------|
| `VideoItemDTO` | UUID, title, даты, meta (resolution/duration/size/bitrate), poster URL, expiredAt/expiredInterval |
| `VideoDetailsDTO` | VideoItemDTO + presets[] + tasks[] |
| `TaskItemDTO` | ID, videoId/Title, presetId/codec/format/title, height, status, progress, даты, waitingTariff* |
| `PresetItemDTO` | id, title, videoCodec, audioCodec, format, bitrate (карта height→Mbps) |
| `ScheduledTaskDTO` | taskId, userId, videoId — используется в планировщике |
| `MercureMessageDTO` | action, entity, id, userId, payload |
| `TranscodeStartContextDTO` | task, video, preset, пути к файлам, timeStart |
| `TranscodeReportDTO` | cancelled, exitCode, stderr и пр. |
| `StorageRealtimePayloadDTO` | текущий и максимальный объём хранилища |
| `FlashNotificationDTO` | realtime flash-уведомление для фронтенда |

### Application Events

Каждый хендлер диспатчит события в `messenger.bus.event`: `*Start`, `*Success`, `*Fail` для операций с видео и задачами. `ApplicationEventLoggerHandler` логирует все такие события.

### Services

| Сервис | Описание |
|--------|----------|
| `VideoRealtimeNotifier` | Публикует `VideoItemDTO` в Mercure |
| `TaskRealtimeNotifier` | Публикует `TaskItemDTO` в Mercure |
| `StorageRealtimeNotifier` | Публикует обновление квоты хранилища в Mercure |
| `FlashRealtimeNotifier` | Публикует flash-уведомление в Mercure |
| `TranscodeTaskPreparationService` | Подготавливает контекст транскодирования (пути, task.status = running) |
| `TranscodeProcessService` | Запускает ffmpeg-процесс, следит за прогрессом |
| `TranscodeTaskFinalizationService` | Финализирует задачу (success/failure/cancelled) |
| `DeletedVideoCleanupService` | Удаляет медиафайлы soft-deleted видео (daily cron) |
| `DeletedTaskCleanupService` | Удаляет файлы soft-deleted задач (daily cron) |

### Exceptions

`VideoNotFoundException`, `TaskNotFoundException`, `PresetNotFoundException`, `UserNotFoundException`, `VideoAccessDeniedException`, `TranscodeAccessDeniedException`, `TaskCancelAccessDeniedException`, `TaskDownloadAccessDeniedException`, `HeightExceedsTariffException`, `PresetHeightNotAvailableException`, `StorageSizeExceedsQuota`, `TaskCreationFailedException`, `InvalidUuidException`, `QueryException`

---

## 3. Infrastructure Layer (`src/Infrastructure`)

### Persistence (Doctrine)

Разделение на **Domain Entity** и **Doctrine Entity** с конвертацией через Mapper:

| Domain Entity | Doctrine Entity | Mapper |
|--------------|----------------|--------|
| `Video` | `VideoEntity` | `VideoMapper` |
| `Task` | `TaskEntity` | `TaskMapper` |
| `Preset` | `PresetEntity` | `PresetMapper` |
| `User` | `UserEntity` | `UserMapper` |
| `Tariff` | `TariffEntity` | `TariffMapper` |
| — | `LogEntity` | — |

Все Doctrine-репозитории реализуют соответствующие доменные интерфейсы.  
`PaginatedRepositoryTrait` — общий трейт для постраничной выборки.  
`ScheduledTaskReadRepositoryInterface` реализован в Doctrine-слое и возвращает `ScheduledTaskDTO[]` с учётом квот тарифа (delay, instance).

### Storage

`StorageInterface` (`Domain/Video/Service/Storage`) — два бэкенда:
- `FilesystemStorage` — локальная ФС (dev)
- `S3Storage` — S3-совместимое хранилище (prod)

Методы: `publicUrl()`, `previewKey()`, `sourceKey()`, `delete()` и пр.

### Ffmpeg

| Класс | Описание |
|-------|----------|
| `Transcode` | Строит массив аргументов `ffmpeg` (scale, codec, bitrate, faststart) |
| `VideoMetadataExtractor` | Запускает `ffprobe`, парсит JSON, возвращает width/height/duration/size/codec |
| `VideoPreviewGenerator` | Генерирует постер из первого кадра |
| `SymfonyProcessRunner` | Обёртка над `Symfony\Component\Process` |

Поддерживаемые видеокодеки: `h264` (libx264), `h265` (libx265), `vp9` (libvpx-vp9), `av1` (libaom-av1).  
Аудиокодеки: `aac`, `opus` (libopus).

### Mercure

`HttpMercurePublisher` — реализует `MercurePublisherInterface`, публикует SSE-сообщения через HTTP POST на внутренний Mercure Hub.  
`MercureTokenService` — генерирует JWT-токены для publisher и subscriber.

### Security

| Класс | Описание |
|-------|----------|
| `ApiTokenService` | Создаёт и валидирует Access/Refresh HMAC-токены (HMAC-SHA256, без JWT-библиотеки) |
| `ApiTokenAuthenticator` | Symfony Authenticator, читает `Bearer` из заголовка `Authorization` |
| `MercureTokenService` | JWT для Mercure publisher/subscriber |
| `VideoAccessVoter` | Voter: `CAN_VIEW_DETAILS`, `CAN_EDIT`, `CAN_START_TRANSCODE`, `CAN_DOWNLOAD_TRANSCODE` |
| `UserSessionAuditListener` | Логирует вход/выход пользователя |

### Logging

`LogServiceInterface` — интерфейс `log(name, action, ?Uuid, level, text, context)`.  
`CompositeLogService` — fan-out по нескольким реализациям:
- `DoctrineLogService` — пишет в таблицу `LogEntity`
- `PromtailLogService` — отправляет JSON-строки в Promtail (Loki)
- `TelegramLogService` — отправляет алерты в Telegram
- `EnrichContextLogDecorator` — добавляет общий контекст (env, user и пр.)

### Upload

`TusPostFinishListener` — слушает событие завершения Tus-загрузки, диспатчит `CreateVideo` command.  
`TusCleanupService` — удаляет expired Tus-chunks (hourly cron).

### Task Cancellation

`TaskCancellationTrigger` — флаг отмены задачи через Redis-кэш. `TranscodeVideoHandler` проверяет флаг до старта ffmpeg и во время выполнения.

### Admin Audit

`AdminCrudAuditListener` — логирует все EasyAdmin CRUD-действия через `LogServiceInterface`.

---

## 4. Presentation Layer (`src/Presentation`)

### API Controllers (`Controller/Api/`)

Все API-контроллеры требуют `IS_AUTHENTICATED_FULLY` и возвращают JSON через трейт `ApiJsonResponseTrait`.

#### `VideoApiController` — `/api/video`

| Метод | URL | Описание |
|-------|-----|----------|
| `GET` | `/api/video/` | Список видео (пагинация) |
| `GET` | `/api/video/{id}` | Детали видео (+ presets, tasks) |
| `POST` | `/api/video/{id}/transcode/{presetId}/{height}` | Запуск/рестарт транскодирования |
| `PATCH` | `/api/video/{id}` | Переименование видео |
| `DELETE` | `/api/video/{id}` | Удаление видео |

#### `TaskApiController` — `/api/task`

| Метод | URL | Описание |
|-------|-----|----------|
| `GET` | `/api/task/` | Список задач (пагинация) |
| `POST` | `/api/task/{id}/cancel` | Отмена задачи |

#### `AuthApiController` — `/api/auth`

| Метод | URL | Описание |
|-------|-----|----------|
| `POST` | `/api/auth/token` | Получение Access + Refresh токенов по email/password |
| `POST` | `/api/auth/refresh` | Обновление токенов по Refresh-токену |

#### `ContactApiController` — `/api/contact`

Отправка контактного сообщения (диспатчит `TelegramMessage`).

### Web Controllers

| Контроллер | Маршрут | Описание |
|-----------|---------|----------|
| `HomeController` | `/` | Главная SPA (Vue 3) |
| `VideoController` | `/video/{uuid}` | Страница деталей видео (Vue 3) |
| `TaskController` | `/task/{id}/download` | Редирект на скачивание файла транскодирования |
| `TariffController` | `/tariffs` | Страница тарифов (Twig) |
| `UploadController` | `/upload` | Tus Upload endpoint |
| `ProfileController` | `/profile` | Профиль пользователя |
| `SecurityController` | `/login`, `/logout` | Авторизация / выход |
| `GoogleController` | `/connect/google`, `/connect/google/check` | Google OAuth |
| `SPAController` | base class | Генерирует конфиг SPA (токены, маршруты, тариф, Mercure) |

### Admin Controllers (`Controller/Admin/`)

EasyAdmin CRUD: `DashboardController`, `UserCrudController`, `VideoCrudController`, `TaskCrudController`, `PresetCrudController`, `TariffCrudController`, `LogCrudController`.

### Console Commands

| Команда | Расписание | Описание |
|---------|-----------|----------|
| `app:minute` | каждую минуту | Диспатчит `StartTaskScheduler`; mutex 15 мин |
| `app:hour` | каждый час | Tus cleanup + удаление expired видео/задач; mutex |
| `app:day` | раз в день | Удаление медиафайлов soft-deleted видео и задач; mutex |
| `app:smoke-prod` | по необходимости | Smoke-test prod окружения |
| `app:smoke-result` | по необходимости | Вывод результата smoke-test |

### Validators

`AtLeastOneAdmin` / `AtLeastOneAdminValidator` — проверяет, что в системе остаётся хотя бы один администратор.  
`PresetBitrateJsonConstraint` / `PresetBitrateJsonValidator` — валидирует JSON битрейтов пресета.

---

## Технологический стек

| Технология | Роль |
|-----------|------|
| PHP 8.4 + Symfony 7.x | API, бизнес-логика |
| PostgreSQL | Основная реляционная БД |
| Redis | Symfony Messenger transport, Symfony Lock, флаги отмены задач |
| Symfony Messenger | Command Bus (sync/async), Event Bus |
| Mercure | SSE push-уведомления на фронтенд |
| ffmpeg / ffprobe | Транскодирование и извлечение метаданных |
| TusPhp | Сервер возобновляемых загрузок |
| S3 / Local FS | Хранилище медиафайлов |
| Loki + Promtail + Grafana | Логирование и мониторинг |
| Telegram Bot API | Алерты |
| Google OAuth | Авторизация через Google |

---

## Тестирование (phpUnit)

tests/
├── Domain/
│   ├── Entity/              ← Fake builders (VideoFake, TaskFake, PresetFake, PaymentFake)
│   ├── Shared/
│   ├── User/
│   │   ├── Entity/UserTest.php, TariffTest.php
│   │   ├── Exception/
│   │   └── ValueObject/     ← One *Test.php per VO
│   └── Video/
│       ├── DTO/
│       ├── Entity/VideoTest.php, TaskTest.php, PresetTest.php
│       ├── Event/
│       ├── Exception/VideoExceptionTest.php
│       └── ValueObject/     ← One *Test.php per VO (13 files)
├── Infrastructure/
│   ├── Persistence/Doctrine/Entity/TariffFake.php
│   ├── Security/
│   ├── Admin/
│   └── Logging/
├── Application/
│   └── Query/               ← Query handler tests
├── Presentation/
├── Unit/                    ← Unit tests for services (VideoRealtimeNotifier, etc.)
└── bootstrap.php

## Рабочие процессы (Workflows)

### 1. Загрузка видео

```
Uppy (front) → POST /upload (Tus) → TusPostFinishListener
  → CreateVideo command → CreateVideoHandler
    → ExtractVideoMetadata command (async worker)
    → CreateVideoPreview command (async worker)
  → VideoRealtimeNotifier → Mercure → SPA
```

### 2. Транскодирование

```
User → POST /api/video/{id}/transcode/{presetId}/{height}
  → StartTranscodeHandler
    → валидация (video, user, preset, tariff, height, quota)
    → create/restart Task entity
    → StartTaskScheduler command
      → StartTaskSchedulerHandler
        → ScheduledTaskReadRepository.getScheduled()
        → TranscodeVideo command (per task, async worker)
          → TranscodeVideoHandler
            → mutex (Symfony Lock)
            → cancellation check
            → storage quota check
            → TranscodeTaskPreparationService.prepare()
            → TranscodeProcessService.run() [ffmpeg]
            → TranscodeTaskFinalizationService.handle*()
            → TaskRealtimeNotifier → Mercure → SPA
            → StartTaskScheduler (re-schedule remaining)
```

### 3. Отмена задачи

```
User → POST /api/task/{id}/cancel
  → TaskCancelHandler
    → TaskCancellationTrigger.request(taskId)  [Redis flag]
  → TranscodeVideoHandler (next tick) → проверяет флаг → task.cancel()
    → TaskRealtimeNotifier → Mercure → SPA
```

### 4. Обновление в реальном времени

```
Любое изменение Video/Task/Storage
  → *RealtimeNotifier.notify*()
    → PublishMercureMessage command
      → HttpMercurePublisher → Mercure Hub (SSE)
        → frontend connectMercure.js → app:video / app:task / app:storage event
```

### 5. Cron-задачи

```
app:minute → StartTaskScheduler (планировщик задач)
app:hour   → TusCleanupService + deleteExpiredVideosAndTasks
app:day    → DeletedVideoCleanupService + DeletedTaskCleanupService
```
