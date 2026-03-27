# AGENTS.md — AI Coding Agent Guide for video-transcoder

e2e tests:
- **e2e** cd /root/video-transcoder/develop && bash release.check.sh

Run phpunit and composer stan after every backend change.
- **phpunit** - docker exec -i develop-php-1 vendor/bin/phpunit tests/
- **stan** - docker exec -i develop-php-1 composer stan

## Architecture Overview
- **Domain-Driven Design (DDD)**: The backend (Symfony) is organized by domain boundaries: `Domain`, `Application`, `Infrastructure`, `Presentation`.
- **Core Components**:
  - **Frontend**: Twig pages mount Vue SPA modules from `develop/symfony/assets/home/`; uploads still use Uppy + tus chunking.
  - **API**: Symfony app (`develop/symfony/`) exposes REST endpoints and handles business logic.
  - **Workers**: Symfony Messenger consumers (auto-scaled) process transcoding jobs using ffmpeg.
  - **Persistence**: PostgreSQL (see `postgres.yaml`), Doctrine ORM, entities in `Domain`/`Infrastructure`.
  - **Messaging**: Symfony Messenger transports are Redis-based in current app config (`develop/symfony/.env`, `config/packages/messenger.yaml`); Deprecated RabbitMQ manifests are still present in `k8s/rabbitmq.yaml`.
  - **Cloud/Infra**: Terraform (`tf/`), Kubernetes manifests (`k8s/`), Docker image build contexts (`develop/docker/`).

## Data & Workflow
- **Upload Flow**: Uppy JS client → `/upload` (Tus) → `UploadController` (Presentation) → `TusPostFinishListener` (Infrastructure) → `CreateVideo` command (Application) → Entity persisted (Domain/Infrastructure).
- **Transcoding**: User triggers task → Task entity created → Message dispatched to queue → ffmpeg worker picks up and processes.
- **Realtime Updates**: Backend publishes Mercure updates per user/topic; frontend consumes them via `assets/home/connectMercure.js` and updates Videos/Tasks views.
- **Role Model**: Guest (browse), User (manage own videos, submit tasks), Admin (CRUD, monitor, manage presets/tasks/users).
- **Quotas**: S3 storage and parallel task limits per user/tariff, enforced in business logic.

## Developer Workflows
- **Build Docker Images**: `develop/docker/yc-php/build.sh`, `develop/docker/yc-ffmpeg/build.sh`, `develop/docker/yc-nginx/build.sh` (tagged, pushed to registry).
- **Local Dev**: Use `develop/docker-compose.yml` to spin up stack (API, DB, Redis, Mercure, Nginx, etc.).
- **Symfony Commands**: Run via `docker exec -it develop-php-1 php bin/console ...` (see `.aiassistant/rules/docker.md`).
- **Tests**: Run PHPUnit in container: `docker exec -it develop-php-1 php vendor/bin/phpunit tests/Domain/Video/ValueObject`.
- **Kubernetes**: Apply manifests with `kubectl apply -k k8s/`, monitor with `kubectl get pods -w`, logs with `kubectl logs ...` (see `k8s/txt.txt`).
- **Terraform**: Infra as code in `tf/`, main entry is `main.tf`.

## Project-Specific Patterns & Conventions
- **DDD Layering**: `Domain` (pure logic), `Application` (commands/queries), `Infrastructure` (adapters, listeners), `Presentation` (controllers/views).
- **Event-Driven**: Use of Symfony Messenger for async commands/events (see `TusPostFinishListener`, `CreateVideo`).
- **DTO Mapping**: Data transfer objects (DTOs) in `Application/DTO` map domain entities for API/UI.
- **Entity Mapping**: Doctrine entities in `Infrastructure/Persistence/Doctrine`, mapped to domain models.
- **Preset/Task/Video**: Presets define transcoding options; Tasks link Videos and Presets, track status/progress.
- **Chunked Uploads**: Uppy + tus protocol for large file uploads, handled by `TusPhp` server.
- **Realtime UI Sync**: `Application/Command/Mercure` + `Infrastructure/Mercure/HttpMercurePublisher` publish task/video updates; frontend listens and patches tab/detail state.
- **Admin UI**: EasyAdmin for CRUD (see `DashboardController`, `TaskCrudController`).

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
- `develop/symfony/src/` — Main backend code (DDD structure)
- `develop/symfony/templates/` — Twig templates (UI)
- `develop/symfony/assets/home/` — Vue SPA modules for Home/Video Details
- `develop/symfony/config/packages/messenger.yaml` — Async transport routing/config
- `develop/docker/yc-php/`, `develop/docker/yc-ffmpeg/`, `develop/docker/yc-nginx/` — Docker build contexts
- `k8s/`, `tf/` — Infrastructure as code
- `.aiassistant/rules/docker.md` — Container usage conventions
- `README.md` — High-level project goals and scenarios

## Examples
- **Add a new transcoding preset**: Implement in `Domain/Video/Entity/Preset.php`, expose via admin CRUD, persist via Doctrine entity.
- **Add a new async job**: Define command in `Application/Command`, dispatch via Messenger, handle in consumer.
- **Enforce quota**: Check limits in Application/Domain before persisting new tasks or uploads.

