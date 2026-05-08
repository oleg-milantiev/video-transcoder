# AGENTS.md — AI Coding Agent Guide for video-transcoder

e2e tests:
- **e2e** cd /root/video-transcoder/develop && bash release.check.sh

Run phpunit and composer stan after every backend change.
- **phpunit** - docker exec -i develop-php-1 vendor/bin/phpunit tests/
- Find and always fix phpUnit notices by adding --debug to the command. Do not add ignore attributes, fix notices and deprecations.
- Check and improve code coverage by running phpunit with --coverage-text
- **stan** - docker exec -i develop-php-1 composer stan
- do not use -v or --verbose keys with phpunit command. Use --debug instead.
- do not create mock if no expected behavior is defined. Use stub instead.

## Coverage:

Check Presentation coverage with:
docker exec -i -e XDEBUG_MODE=coverage develop-php-1 vendor/bin/phpunit tests/ --coverage-text 2>&1 | grep -E "App\\\\Presentation" -A2


## Architecture Overview
- **Domain-Driven Design (DDD)**: The backend (Symfony) is organized by domain boundaries: `Domain`, `Application`, `Infrastructure`, `Presentation`.
- **Core Components**:
  - **Frontend**: Single Page Application (SPA) built with **Vue 3** and **Symfony AssetMapper** (no bundler yet).
    - Modular architecture in `assets/home/`: each tab/page is split into `state.js` (Vue `ref()`s), `actions.js` (API + realtime), `render.js` (Vue `h()` render functions), `view.js` (optional `defineComponent` composition).
    - Tabs: **Upload** (Uppy/tus), **Videos** (paginated list, poster, delete), **Tasks** (paginated list with actions).
    - **Video Details** page (`video-details/`): poster, rename (SweetAlert2 modal), preset resolution buttons with estimated file size, transcoding tasks table with Cancel/Download/Transcode actions.
    - Real-time updates via **Mercure**: `connectMercure.js` dispatches `app:video`, `app:task`, `app:storage` CustomEvents; `realtime/` parsers extract payloads; `bindHomeRealtime` / `bindVideoDetailsRealtime` apply updates directly to reactive state.
    - **Realtime DTO = Initial DTO**: the payload of Mercure `app:video` / `app:task` messages is the same shape as the DTO returned by the initial API fetch, so updates are applied via spread-merge without extra normalization.
    - Resumable uploads via **Uppy + tus**.
    - **API**: Symfony app (`develop/symfony/`) exposes REST endpoints and handles business logic.
  - **Workers**: Symfony Messenger consumers (auto-scaled) process transcoding jobs using ffmpeg.
  - **Persistence**: PostgreSQL (see `postgres.yaml`), Doctrine ORM, entities in `Domain`/`Infrastructure`.
  - **Messaging**: Symfony Messenger transports are Redis-based (`develop/symfony/.env`, `config/packages/messenger.yaml`). RabbitMQ is deprecated; manifest remains in `k8s/rabbitmq.yaml` but is not used.
  - **Cloud/Infra**: Terraform (`tf/`), Kubernetes manifests (`k8s/`), Docker image build contexts (`develop/docker/`).

## Data & Workflow
- **Upload Flow**: Uppy JS client → `/upload` (Tus) → `UploadController` (Presentation) → `TusPostFinishListener` (Infrastructure) → `CreateVideo` command (Application) → Entity persisted (Domain/Infrastructure).
- **Transcoding**: User triggers task → Task entity created → Message dispatched to queue → ffmpeg worker picks up and processes.
- **Realtime Updates**: Backend publishes Mercure updates per user/topic; frontend consumes them via `assets/home/connectMercure.js` and updates Videos/Tasks views.
- **Role Model**: Guest (browse), User (manage own videos, submit tasks), Admin (CRUD, monitor, manage presets/tasks/users).
- **Quotas**: S3 storage and parallel task limits per user/tariff, enforced in business logic.
- **Google OAuth**: Login/register via Google — `GoogleController` (`/connect/google`, `/connect/google/check`) + `Infrastructure/Google/GoogleAuthenticator`. New users auto-assigned Free tariff (`TariffEntity` reference).

## Developer Workflows
- **Build Docker Images**: `develop/docker/yc-php/build.sh`, `develop/docker/yc-ffmpeg/build.sh`, `develop/docker/yc-nginx/build.sh` (tagged, pushed to registry).
- **Local Dev**: Use `develop/docker-compose.yml` to spin up stack (API, DB, Redis, Mercure, Nginx, etc.).
- **Frontend Tests**: Run via Node.js with ESM loader: `bash develop/symfony/assets/tests.sh`.
- **Symfony Commands**: Run via `docker exec -it develop-php-1 php bin/console ...` (see `.aiassistant/rules/docker.md`).
- **Tests**: Run PHPUnit in container: `docker exec -it develop-php-1 php vendor/bin/phpunit tests/Domain/Video/ValueObject`.
- **Kubernetes**: Apply manifests with `kubectl apply -k k8s/`, monitor with `kubectl get pods -w`, logs with `kubectl logs ...` (see `k8s/txt.txt`).
- **Terraform**: Infra as code in `tf/`, main entry is `main.tf`.

## Project-Specific Patterns & Conventions
- **DDD Layering**: `Domain` (pure logic), `Application` (commands/handlers, DTOs), `Infrastructure` (Persistence/Doctrine mappers, S3/Local storage, Ffmpeg wrappers), `Presentation` (API Controllers, EasyAdmin).
- **Domain vs Infrastructure Entities**: Clear separation between pure Domain models and Doctrine-mapped Infrastructure entities, using Mappers for conversion.
- **Event-Driven & Async**: Extensive use of Symfony Messenger for async tasks like transcoding and metadata extraction (see `TusPostFinishListener`, `CreateVideo`).
- **DTO Mapping**: Data transfer objects (DTOs) in `Application/DTO` map domain entities for API/UI.
- **Entity Mapping**: Doctrine entities in `Infrastructure/Persistence/Doctrine`, mapped to domain models.
- **Preset/Task/Video**: Presets define transcoding options; Tasks link Videos and Presets, track status/progress.
- **Chunked Uploads**: Uppy + tus protocol for large file uploads, handled by `TusPhp` server.
- **Realtime UI Sync**: `connectMercure.js` dispatches `app:video`, `app:task`, `app:storage` CustomEvents from Mercure SSE messages. `realtime/` parsers (`appVideoMessage.js`, `appTaskMessage.js`, `appStorageMessage.js`) extract the payload. `bindHomeRealtime` / `bindVideoDetailsRealtime` wire these into `applyVideoRealtimeUpdate` / `applyTaskRealtimeUpdate` functions in each module's `actions.js`. The Mercure message format is `{ action, entity, id, payload }` where `payload` matches the same DTO shape as the initial REST response.
- **Admin UI**: EasyAdmin for CRUD (see `DashboardController`, `UserCrudController`, `VideoCrudController`, `TaskCrudController`, `PresetCrudController`, `TariffCrudController`, `LogCrudController`). All CRUD actions are audited via `AdminCrudAuditListener`.
- **Tariff Page**: `TariffController` renders `/tariffs` via `tariff/index.html.twig`; frontend tariff view in `assets/home/tariff/` (render.js, view.js).
- **Frontend Tabs**: `assets/home/tabs/` contains sub-modules for `videos/`, `upload/`, `tasks/` (each with state/actions/render), plus `TariffHint.js` and reusable `shared.js`. The `task/` module (`actions.js` + `render.js`) provides shared task API actions and action-button rendering reused by both `tabs/tasks/` and `video-details/`.
- **Realtime DTO parity**: Mercure `app:video` and `app:task` payloads have the same structure as the corresponding API response DTOs. `applyVideoRealtimeUpdate` / `applyTaskRealtimeUpdate` do a direct spread-merge; no separate normalization layer is needed.
- **Application Sub-layers**: Beyond Command/Handler/DTO, the Application layer also includes `Event/`, `EventListener/`, `Exception/`, `Factory/`, `Helper/`, `Logging/`, `Query/`, `QueryHandler/`, `Response/`, and `Service/` directories.
- **Security**: Two auth strategies — session (web) and Bearer token (API). `ApiTokenService` issues HMAC-SHA256 Access (1h) + Refresh (24h) tokens. `ApiTokenAuthenticator` validates Bearer on every API request. `VideoAccessVoter` enforces per-video permissions (`CAN_VIEW_DETAILS`, `CAN_EDIT`, `CAN_START_TRANSCODE`, `CAN_DOWNLOAD_TRANSCODE`).
- **Cron Commands**: `app:minute` (dispatch `StartTaskScheduler`), `app:hour` (Tus cleanup + expire videos/tasks), `app:day` (delete soft-deleted media files). All use Symfony Lock to prevent overlap.
- **Task Cancellation**: `TaskCancelHandler` sets a Redis flag via `TaskCancellationTrigger`; `TranscodeVideoHandler` checks the flag before and during ffmpeg execution.
- **Logging**: `CompositeLogService` fans out to `DoctrineLogService` (DB), `PromtailLogService` (Loki), `TelegramLogService` (alerts), `EnrichContextLogDecorator`.
- **SPAController**: Base controller that injects tokens, Mercure config, route templates, and tariff data into the Twig template as a `config` JSON object consumed by Vue SPA.

## Integrations & External Dependencies
- **TusPhp**: Handles resumable uploads.
- **ffmpeg**: Used in worker containers for transcoding.
- **Redis (Messenger transport)**: Async queue transport used by Symfony Messenger in current dev/release config.
- **Mercure**: Realtime publish/subscribe for task/video status updates.
- **RabbitMQ**: Deprecated, but Kubernetes/infrastructure manifest exists (`k8s/rabbitmq.yaml`).
- **PostgreSQL**: Main data store.
- **S3-compatible storage**: For video files (see quota logic).
- **Terraform/Kubernetes**: For cloud provisioning and orchestration.

## Key Files & Directories
- `develop/symfony/src/` — Main backend code (DDD structure); API controllers in `Presentation/Controller/Api/` (`VideoApiController`, `TaskApiController`, `AuthApiController`, `ContactApiController`)
- `develop/symfony/templates/` — Twig templates (UI)
- `develop/symfony/assets/home/` — Vue SPA modules (state, actions, render logic)
- `develop/symfony/assets/tests/` — Frontend unit tests (Node.js ESM)
- `develop/symfony/config/packages/messenger.yaml` — Async transport routing/config
- `develop/docker/yc-php/`, `develop/docker/yc-ffmpeg/`, `develop/docker/yc-nginx/` — Docker build contexts
- `k8s/`, `tf/` — Infrastructure as code
- `.aiassistant/rules/docker.md` — Container usage conventions
- **[BACKEND.md](BACKEND.md)** — Detailed backend architecture (DDD layers), Application services, Domain logic, and Workflows
- **[FRONTEND.md](FRONTEND.md)** — Frontend architecture, Vue SPA modules, and AssetMapper integration

## Examples
- **Add a new transcoding preset**: Implement in `Domain/Video/Entity/Preset.php`, expose via admin CRUD, persist via Doctrine entity.
- **Add a new async job**: Define command in `Application/Command`, dispatch via Messenger, handle in consumer.
- **Enforce quota**: Check limits in Application/Domain before persisting new tasks or uploads.
