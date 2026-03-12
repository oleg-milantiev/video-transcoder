# AGENTS.md — AI Coding Agent Guide for video-transcoder

## Architecture Overview
- **Domain-Driven Design (DDD)**: The backend (Symfony) is organized by domain boundaries: `Domain`, `Application`, `Infrastructure`, `Presentation`.
- **Core Components**:
  - **Frontend**: Static VueJS (planned), currently a Twig/JS interface using Uppy for chunked uploads via tus protocol.
  - **API**: Symfony app (`develop/symfony/`) exposes REST endpoints and handles business logic.
  - **Workers**: Symfony Messenger consumers (auto-scaled) process transcoding jobs using ffmpeg.
  - **Persistence**: PostgreSQL (see `postgres.yaml`), Doctrine ORM, entities in `Domain`/`Infrastructure`.
  - **Messaging**: RabbitMQ for async job queueing (see `rabbitmq.yaml`).
  - **Cloud/Infra**: Terraform (`tf/`), Kubernetes manifests (`k8s/`), Docker images (`build/`).

## Data & Workflow
- **Upload Flow**: Uppy JS client → `/upload` (Tus) → `UploadController` (Presentation) → `TusPostFinishListener` (Infrastructure) → `CreateVideo` command (Application) → Entity persisted (Domain/Infrastructure).
- **Transcoding**: User triggers task → Task entity created → Message dispatched to queue → ffmpeg worker picks up and processes.
- **Role Model**: Guest (browse), User (manage own videos, submit tasks), Admin (CRUD, monitor, manage presets/tasks/users).
- **Quotas**: S3 storage and parallel task limits per user/tariff, enforced in business logic.

## Developer Workflows
- **Build Docker Images**: `build/yc-php/build.sh`, `build/yc-ffmpeg/build.sh` (tagged, pushed to registry).
- **Local Dev**: Use `develop/docker-compose.yml` to spin up stack (API, DB, RabbitMQ, Nginx, etc.).
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
- **Admin UI**: EasyAdmin for CRUD (see `DashboardController`, `TaskCrudController`).

## Integrations & External Dependencies
- **TusPhp**: Handles resumable uploads.
- **ffmpeg**: Used in worker containers for transcoding.
- **RabbitMQ**: Message broker for async processing.
- **PostgreSQL**: Main data store.
- **S3-compatible storage**: For video files (see quota logic).
- **Terraform/Kubernetes**: For cloud provisioning and orchestration.

## Key Files & Directories
- `develop/symfony/src/` — Main backend code (DDD structure)
- `develop/symfony/templates/` — Twig templates (UI)
- `build/yc-php/`, `build/yc-ffmpeg/` — Docker build contexts
- `k8s/`, `tf/` — Infrastructure as code
- `.aiassistant/rules/docker.md` — Container usage conventions
- `README.md` — High-level project goals and scenarios

## Examples
- **Add a new transcoding preset**: Implement in `Domain/Video/Entity/Preset.php`, expose via admin CRUD, persist via Doctrine entity.
- **Add a new async job**: Define command in `Application/Command`, dispatch via Messenger, handle in consumer.
- **Enforce quota**: Check limits in Application/Domain before persisting new tasks or uploads.

