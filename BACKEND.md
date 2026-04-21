# Backend Architecture (Symfony DDD)

Бэкенд проекта построен на принципах **Domain-Driven Design (DDD)** и разделен на четыре основных слоя: `Domain`, `Application`, `Infrastructure`, и `Presentation`.

---

## 1. Domain Layer (`src/Domain`)
Содержит ядро бизнес-логики. Не зависит от фреймворков и внешних библиотек.

- **Entity**: "Чистые" PHP-классы (`Video`, `Task`, `User`, `Preset`). Содержат бизнес-правила и инварианты.
- **ValueObject**: Неизменяемые объекты (`Uuid`, `TaskStatus`, `Bitrate`, `FileExtension`).
- **Repository (Interfaces)**: Интерфейсы для работы с коллекциями сущностей. Реализации находятся в слое Infrastructure.
- **Exception**: Доменные исключения (`VideoAlreadyDeleted`, `UserNotFound`).

---

## 2. Application Layer (`src/Application`)
Координирует выполнение задач. Не содержит бизнес-логики, а делегирует её доменным объектам.

- **Command & CommandHandler**: Используется **Symfony Messenger** для реализации паттерна Command Bus. Команды (`CreateVideo`, `TranscodeVideo`) выражают намерения, хендлеры выполняют их.
- **DTO (Data Transfer Objects)**: Объекты для передачи данных между слоями (`VideoItemDTO`, `TaskItemDTO`). Помогают сохранять контракт API стабильным.
- **Service**: Сервисы приложения для задач, выходящих за рамки одной сущности (например, `TranscodeProcessService`, `VideoRealtimeNotifier`).
- **Query & QueryHandler**: (Если есть) Реализация паттерна CQRS для получения данных.

---

## 3. Infrastructure Layer (`src/Infrastructure`)
Реализует детали технологий (БД, Файловая система, Внешние API).

- **Persistence (Doctrine)**: 
  - Разделение на **Domain Entity** и **Infrastructure Entity** (Doctrine-маппинг).
  - **Mappers**: Преобразование доменных объектов в объекты Doctrine и обратно.
  - Реализации репозиториев (`VideoRepository`, `UserRepository`).
- **Storage**: Абстракция над файловой системой. Поддержка **Local FS** и **S3-compatible** хранилищ.
- **Ffmpeg**: Обертки над системными вызовами `ffmpeg` и `ffprobe` (`VideoMetadataExtractor`, `Transcode`).
- **Mercure**: Реализация `MercurePublisherInterface` для отправки real-time уведомлений через протокол Mercure.
- **Upload**: Интеграция с протоколом **Tus**. `TusPostFinishListener` перехватывает окончание загрузки и инициирует создание видео в системе.
- **Security**: Настройка авторизации (Session + Bearer Token), реализация `ApiTokenService`.

---

## 4. Presentation Layer (`src/Presentation`)
Входные точки в приложение.

- **Controller**: 
  - `Api/`: REST-контроллеры, возвращающие JSON (`VideoApiController`, `TaskApiController`).
  - `Admin/`: CRUD-интерфейс на базе **EasyAdmin**.
  - `HomeController`, `SPAController`: Рендеринг Twig-шаблонов и монтирование SPA.
- **Console**: Symfony Commands для CLI-инструментов.
- **Validator**: Кастомные валидаторы для данных.

---

## Технологический стек & Интеграции

- **Symfony Messenger**: Основной механизм для асинхронных задач (транскодирование, извлечение метаданных). Транспорт — **Redis**.
- **PostgreSQL**: Основная реляционная БД.
- **Tus.php**: Сервер для протокола Tus (резумабельные загрузки).
- **Mercure**: Hub для push-уведомлений на фронтенд.
- **ffmpeg / ffprobe**: Обработка видеофайлов в воркерах.

---

## Рабочие процессы (Workflows)

1. **Загрузка видео**: Uppy (front) -> Tus Server -> `TusPostFinishListener` -> `CreateVideo` command -> `ExtractVideoMetadata` command.
2. **Транскодирование**: User (API) -> `TaskController` -> Create Task -> Dispatch `TranscodeVideo` message -> Worker picks up -> ffmpeg process -> Finalize task -> Notify via Mercure.
3. **Обновление в реальном времени**: Любое изменение статуса задачи или видео через `VideoRealtimeNotifier` / `TaskRealtimeNotifier` отправляет DTO в Mercure Hub.
