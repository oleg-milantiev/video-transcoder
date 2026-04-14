# Frontend Architecture

В проекте используется **Vue 3** в связке с **Symfony AssetMapper**. Это означает отсутствие бандлера (Webpack/Vite) в традиционном понимании; браузер загружает ES-модули напрямую, а зависимости (Vue, Vue Router, SweetAlert2) берутся из `assets/vendor/`.

## SPA Structure

Весь основной интерфейс пользователя реализован как Single Page Application (SPA), монтируемое в `templates/home/index.html.twig`.

### Основные компоненты (assets/home/)

- `mountHomeSpa.js`: Точка входа. Настраивает **Vue Router**, инициализирует авторизацию (`apiAuth.js`), подключает **Mercure** (`connectMercure.js`) и монтирует приложение.
- `HomeTabsView.js`: Главный экран с табами (Videos, Upload, Tasks).
- `video-details/`: Страница деталей конкретного видео.
- `profile/`: Настройки профиля пользователя.
- `realtime/`: Обработчики входящих сообщений от Mercure (обновление состояния видео и задач).

### Паттерн проектирования модулей

Для обеспечения тестируемости без использования сложного окружения (JSDOM/Vite), каждый модуль (таб или страница) разделен на части:

1. **state.js**: Содержит реактивное состояние (Vue `reactive`) и начальные данные.
2. **actions.js**: Бизнес-логика, API-запросы, обновление состояния.
3. **render.js**: UI-слой, использующий `h()` (render functions) вместо `.vue` шаблонов. Это позволяет избежать компиляции шаблонов на лету и упрощает интеграцию с AssetMapper.
4. **view.js**: (Опционально) Композиция state и render в полноценный Vue-компонент.

### Routing

Используется `vue-router` с `createWebHistory()`:
- `/` — Табы (Видео, Загрузка, Задачи).
- `/video/:uuid` — Детали видео.
- `/profile` — Профиль.

---

## Auth & API

### Двойной контур авторизации

1. **Web-сессия (Symfony `main` firewall)**: Cookie-based сессия для доступа к HTML-страницам и админке.
2. **Bearer token (Symfony `api` firewall)**: 
   - Используется для всех запросов к `/api/*`.
   - Токен (JWT-подобный) передается из Twig в глобальный конфиг `config.token`.
   - `apiAuth.js` управляет хранением и обновлением (refresh) токена.
   - Заголовки `Authorization: Bearer <token>` добавляются автоматически во всех экшенах.

### Контракт API (Response)

- **Success**: `{ "data": { ... } }`
- **Error**: `{ "error": { "code": "...", "message": "...", "details": {} } }`

---

## Real-time (Mercure)

Backend публикует обновления о статусе видео и задач через Mercure.
- `connectMercure.js` подписывается на топики пользователя.
- При получении сообщения вызываются обработчики из `assets/home/realtime/`.
- Обновления применяются напрямую к `state.js` соответствующих модулей, что вызывает автоматический перерендер UI.

---

## Flash Notifications

Глобальная система уведомлений на базе **SweetAlert2**.
- Реализация: `assets/flash/bindFlashNotifications.js`.
- Вызывается через CustomEvent `app:flash` в `window`.
- Поддерживает HTML, картинки, таймеры и различные уровни (success, error, etc.).

---

## Testing

Тесты написаны на чистом Node.js с использованием ESM Loader для маппинга зависимостей.

### Запуск тестов

```bash
# Все тесты фронтенда
bash develop/symfony/assets/tests.sh
```

### Структура тестов (assets/tests/)

- `loader.mjs`: Мапит `import { h } from 'vue'` в физический файл в `assets/vendor/`.
- `*.test.mjs`: Тесты отдельных функций (shared, actions, realtime logic).
- Используется встроенный `node:assert`.

