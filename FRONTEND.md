# Frontend Architecture

В проекте используется **Vue 3** в связке с **Symfony AssetMapper**. Бандлер (Webpack/Vite) не используется — браузер загружает ES-модули напрямую. Зависимости (Vue, Vue Router, SweetAlert2 и др.) хранятся в `assets/vendor/`.

## SPA Structure

Основной интерфейс реализован как Single Page Application, монтируемое в `templates/home/index.html.twig`.

### Файлы точки входа

| Файл | Назначение |
|---|---|
| `mountHomeSpa.js` | Создаёт Vue Router, инициализирует `apiAuth.js`, подключает Mercure (`connectMercure.js`), монтирует приложение |
| `HomeTabsView.js` | Главный экран с табами (Upload / Videos / Tasks). Объединяет состояние всех трёх табов и realtime-биндинги |
| `HomeTabsRender.js` | Рендер-функция (`h()`) главного экрана |
| `legacyHomeWidgets.js` | Инициализация виджетов, не связанных с SPA (контактная форма и т.п.) |

### Директории модулей

```
assets/home/
├── tabs/
│   ├── upload/         — таб загрузки (state, actions, render)
│   ├── videos/         — таб списка видео (state, actions, render)
│   └── tasks/          — таб списка задач (state, actions, render)
├── video-details/      — страница деталей видео (state, actions, render, view, realtime)
├── task/               — общие actions и render для отдельной задачи (используются в video-details и tasks)
├── profile/            — профиль пользователя (state, actions, render, view)
├── tariff/             — страница тарифов (render, view, planCard)
├── realtime/           — парсеры Mercure-сообщений и биндинг событий
├── shared.js           — утилиты (форматирование дат, байт, извлечение ошибок API и т.д.)
├── apiAuth.js          — управление Bearer-токеном
├── connectMercure.js   — подключение к SSE/Mercure
└── mountHomeSpa.js
```

---

## Паттерн проектирования модулей

Каждый таб и страница разбиты на четыре части для тестируемости без JSDOM/bundler:

1. **state.js** — реактивные `ref()`-переменные; чистые данные без side-эффектов.
2. **actions.js** — бизнес-логика, API-запросы (через `authFetch`), мутации состояния, обработка realtime-обновлений.
3. **render.js** — UI-слой на Vue `h()` (render functions). Шаблоны `.vue` не используются — это позволяет работать без компилятора шаблонов и упрощает AssetMapper-интеграцию.
4. **view.js** *(опционально)* — `defineComponent`, связывающий `state + actions + render` в полноценный Vue-компонент.

---

## Карточка видео (tabs/videos)

### Состояние (`createVideosTabState`)

```js
{
  videos: ref([]),
  videosMeta: ref({ page, limit, total, totalPages }),
  videosLoading: ref(false),
  videosError: ref(''),
  videosLoaded: ref(false),
  videoDeletePending: ref({}),
}
```

### Структура элемента `video` (DTO из API и realtime)

| Поле | Описание |
|---|---|
| `uuid` | Идентификатор |
| `title` | Название |
| `poster` | URL постера (или `null`) |
| `meta` | Мета-данные (`_width`, `_height`, `_duration`, ...) |
| `createdAt` | ISO-строка |
| `updatedAt` | ISO-строка |
| `deleted` | `true` если удалено |
| `canBeDeleted` | `true` если можно удалить |

### Рендер (`renderVideosPane`)

- Таблица с колонками: **Poster / Name / Created / Actions**.
- Постер: `<img>` 120px, при `deleted=true` — `filter: saturate(0); opacity: 0.5`.
- Кнопка Delete отображается только если `!video.deleted && video.canBeDeleted`.
- Пагинация: кнопки Prev/Next + счётчик "Page X / Y (total N)".

---

## Детали видео (video-details)

### Состояние (`createVideoDetailsState`)

```js
{
  dto: ref(null),      // полный DTO: { video, tasks, presets }
  loading: ref(false),
  error: ref(''),
  actionError: ref(''),
  activeActionKey: ref(''),
}
```

### DTO страницы деталей

```json
{
  "video": {
    "uuid": "...",
    "title": "...",
    "poster": "...",
    "meta": { "_width": 1920, "_height": 1080, "_duration": 120.5, ... },
    "createdAt": "...",
    "updatedAt": "...",
    "expiredAt": "...",
    "expiredInterval": "P30D",
    "deleted": false
  },
  "tasks": [
    {
      "id": "...",
      "status": "PENDING|STARTING|PROCESSING|COMPLETED|CANCELLED|FAILED",
      "progress": 42,
      "presetId": "...",
      "presetTitle": "...",
      "presetFormat": "mp4",
      "presetVideoCodec": "h264",
      "presetAudioCodec": "aac",
      "height": 1080,
      "videoId": "...",
      "videoTitle": "...",
      "createdAt": "...",
      "updatedAt": "...",
      "expiredAt": "...",
      "waitingTariffInstance": false,
      "waitingTariffDelay": false,
      "willStartAt": null
    }
  ],
  "presets": [
    {
      "id": "...",
      "title": "...",
      "videoCodec": "h264",
      "audioCodec": "aac",
      "format": "mp4",
      "bitrate": { "1080": 5.0, "720": 3.0, "480": 1.5 }
    }
  ]
}
```

> **Важно**: структура DTO, получаемая при начальной загрузке страницы (`GET /api/video/:uuid`), полностью совпадает со структурой payload в realtime-сообщениях от Mercure. Обновления патчируются поверх `state.dto.value` без нормализации.

### Функции рендера

- **Постер видео**: `<img>` до 520px, при `deleted=true` — CSS-класс `video-poster--deleted`.
- **Переименование**: кнопка ✏️ открывает `Swal.fire` с `input: 'text'`; успех приходит через SSE.
- **Секция пресетов**: отображается только если в `meta` есть `_width` и `_height`. Для каждого пресета — ряд кнопок по доступным разрешениям (фильтруются по `tariff.height`). Кнопка зачёркнута и заблокирована, если задача для данного пресета+высоты уже существует.
- **Таблица задач**: Preset / Resolution / Status / Progress / Created / Actions. Статус `PENDING` может содержать иконку `?` с подсказкой о причинах ожидания (тарифные ограничения).
- **Действия над задачей** (`task/render.js → renderTaskAction`):
  - `COMPLETED` → кнопка Download (`<a download>`).
  - `PENDING/STARTING/PROCESSING` → кнопка Cancel.
  - `CANCELLED` → кнопка Transcode (перезапуск).

---

## Таб задач (tabs/tasks)

### Состояние

```js
{
  tasks: ref([]),
  tasksMeta: ref({ page, limit, total, totalPages }),
  tasksLoading: ref(false),
  tasksError: ref(''),
  tasksLoaded: ref(false),
  taskActionKey: ref(''),
}
```

Структура элемента `task` в списке совпадает с элементом из DTO деталей видео (те же поля).

---

## Real-time (Mercure)

### Подключение

`connectMercure.js` создаёт `EventSource` к хабу Mercure с токеном подписчика. Сохраняет ссылку в `window.__mercureEventSource` (устойчиво к Turbo Drive-навигации). При получении сообщения:

1. Генерирует `CustomEvent('mercure:message')` на `window`.
2. По полю `entity` генерирует узкоспециализированный событие: `app:video`, `app:task` или `app:storage`.
3. Если в `payload.notification` есть поле — генерирует `app:flash`.

### Формат сообщения

```json
{ "entity": "video|task|storage", "payload": { ...dto... } }
```

### Парсеры (`realtime/`)

| Файл | Функция | Описание |
|---|---|---|
| `appVideoMessage.js` | `parseAppVideoMessage(msg)` | Проверяет `entity === 'video'`, возвращает `payload` |
| `appTaskMessage.js` | `parseAppTaskMessage(msg)` | Проверяет `entity === 'task'`, возвращает `payload` |
| `appStorageMessage.js` | `parseAppStorageMessage(msg)` | Проверяет `entity === 'storage'`, возвращает `payload` |

### Биндинг

- `bindHomeRealtime(handlers)` — используется в `HomeTabsView`. Слушает `app:task`, `app:video`, `app:storage`. Возвращает функцию отписки.
- `bindVideoDetailsRealtime(handlers)` — используется в `VideoDetailsView`. Слушает `app:task`, `app:video`. Возвращает функцию отписки.

### Применение обновлений

**Video (tabs/videos)** — `applyVideoRealtimeUpdate(payload)`:
- Ищет видео по `payload.videoId` (`video.id` или `video.uuid`).
- Патчит поля: `poster`, `title`, `meta`, `updatedAt`, `deleted`, `canBeDeleted`.

**Task (tabs/tasks)** — `applyTaskRealtimeUpdate(update)`:
- Ищет задачу по `update.taskId` (`task.id`).
- Патчит поля: `status`, `progress`, `createdAt`, `videoTitle`, `presetTitle`.

**Video (video-details)** — `applyVideoRealtimeUpdate(payload)`:
- Payload содержит полный объект `video` (совпадает с `dto.video`).
- Spread-мёрж: `{ ...video, ...payload }` → `state.dto.value.video`.

**Task (video-details)** — `applyTaskRealtimeUpdate(update)`:
- Фильтр по `update.videoId === video.uuid`.
- Если задача найдена — патчит поля: `status`, `progress`, `createdAt`, `updatedAt`, `expiredAt`, `videoTitle`, `waitingTariffInstance`, `waitingTariffDelay`, `willStartAt`.
- Если задача не найдена — добавляет новую запись в `dto.tasks`.

**Storage** — обновляет ограничения Uppy и поля `tariff.storage.now/max` в `HomeTabsView`.

---

## Routing

`vue-router` с `createWebHistory()`:

| Путь | Компонент |
|---|---|
| `/` | `HomeTabsView` (табы Upload / Videos / Tasks) |
| `/video/:uuid` | `VideoDetailsView` |
| `/profile` | `ProfileView` |

Активный таб синхронизируется с query-параметром `?tab=upload|videos|tasks`.

---

## Auth & API

### Двойной контур авторизации

1. **Web-сессия** (`main` firewall) — Cookie-based для HTML-страниц и EasyAdmin.
2. **Bearer token** (`api` firewall) — для всех `/api/*` запросов.
   - Токен передаётся из Twig в глобальный `config.token`.
   - `apiAuth.js` управляет хранением и refresh-логикой токена.
   - `authFetch` автоматически добавляет `Authorization: Bearer <token>`.

### Контракт API

- **Success**: `{ "data": { ... } }` или `{ "items": [...], "total": N, "page": P, "limit": L, "totalPages": T }`
- **Error**: `{ "error": { "code": "...", "message": "...", "details": {} } }`

---

## Flash Notifications

Глобальные уведомления через **SweetAlert2**.
- `assets/flash/bindFlashNotifications.js` подписывается на `window` событие `app:flash`.
- Backend может включить `notification` объект прямо в Mercure payload.

---

## Тарифная страница

- `tariff/view.js` + `tariff/render.js` + `tariff/planCard.js` — отдельная страница `/tariffs`.
- Рендерит карточки тарифных планов с подсветкой текущего плана пользователя.
- `TariffHint.js` (`tabs/TariffHint.js`) — компонент подсказки об ограничениях тарифа, используется в таб Upload.

---

## Testing

Тесты — чистый Node.js с ESM Loader, без JSDOM.

```bash
bash develop/symfony/assets/tests.sh
```

### Структура (`assets/tests/`)

| Файл | Покрывает |
|---|---|
| `loader.mjs` | ESM-мэппинг `vue` / `vue-router` → `assets/vendor/` |
| `shared.test.mjs` | `shared.js` утилиты |
| `realtime.test.mjs` | Парсеры и `applyTaskRealtimeUpdate` / `applyVideoRealtimeUpdate` (video-details) |
| `realtime-storage.test.mjs` | `appStorageMessage` |
| `videos-actions.test.mjs` | `createVideosTabActions`, `applyVideoRealtimeUpdate` |
| `tasks-actions.test.mjs` | `createTasksTabActions`, `applyTaskRealtimeUpdate` |
| `video-details-actions.test.mjs` | `createVideoDetailsActions` |
| `apiAuth.test.mjs` | `apiAuth.js` |
| `uploadHint.test.mjs` | `TariffHint.js` |
| `flash.test.mjs` | Flash-нотификации |

Используется встроенный `node:assert`.
