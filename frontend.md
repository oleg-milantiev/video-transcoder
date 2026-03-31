# Frontend

Пока что я почти не рублю в Vue.js. Почти всё навайбано. Хоть я и планирую вникнуть. Но не в первую очередь.

## Auth

Сейчас в UI используются **два контура авторизации**:

1. **Web-сессия (Symfony `main` firewall)**
   - Нужна для доступа к страницам (`/`, `/video/{uuid}`, `/admin`, ...).
   - Пользователь логинится через форму, дальше работает обычная cookie-based сессия.

2. **Bearer token для API (`/api/*`, Symfony `api` firewall)**
   - API работает stateless и ожидает заголовок:
     ```
     Authorization: Bearer <token>
     ```
   - Токен создаётся на backend (`ApiTokenService`) и прокидывается в SPA через `data-api-bearer-token`.
   - Дальше frontend (Vue/Uppy/fetch) добавляет этот заголовок во все API-запросы.

### Как это выглядит во frontend

- При рендере страниц backend кладёт токен в `data-api-bearer-token`.
- В JS из этого значения формируются auth headers.
- Любой POST/GET в `/api/*` идёт с Bearer.
- Если токена нет или он протух, API возвращает `401`.

### Что внутри токена (важно понимать)

- Формат: подписанный payload (`sub`, `identifier`, `exp`).
- Подпись: HMAC SHA-256 с `kernel.secret`.
- TTL по умолчанию: `3600` секунд.

### Ошибки авторизации API

- Без Bearer: `401` + `{"error":"Missing Bearer token."}`
- Невалидный/просроченный Bearer: `401` + `{"error":"Invalid or expired token."}`


## POST контракт

### Success

```
{
  "data": {
    "...": "..."
  }
}
```

Примеры:

```
{
  "data": {
    "task": {
      "taskId": 15,
      "status": "PENDING"
    }
  }
}
```

```
{
  "data": {
    "task": {
      "id": 21,
      "status": "CANCELLED",
      "cancelledNow": true,
      "cancellationRequested": true
    }
  }
}
```

### Error

```
{
  "error": {
    "code": "SOME_CODE",
    "message": "Human readable message",
    "details": {}
  }
}
```

Коды ошибок, которые сейчас зафиксированы:
- INVALID_UUID
- VIDEO_NOT_FOUND
- PRESET_NOT_FOUND
- USER_NOT_FOUND
- TASK_NOT_FOUND
- TASK_CREATION_FAILED
- ACCESS_DENIED
- QUERY_FAILED
- INTERNAL_ERROR


## app:flash уведомления

Во frontend добавлен глобальный listener на `window` событие `app:flash`.

- Реализация: `develop/symfony/assets/flash/bindFlashNotifications.js`
- Плагин: `SweetAlert2` в режиме toast
- Поддержка: HTML в тексте, ссылки, оформление, картинка (`imageUrl`)

Минимальный пример:

```js
window.dispatchEvent(new CustomEvent('app:flash', {
  detail: {
    level: 'success',
    title: 'Видео готово',
    html: 'Файл собран. <a href="/download/123">Скачать</a>',
    imageUrl: '/uploads/previews/123.jpg',
    timer: 7000,
    position: 'top-end'
  }
}));
```

Поддерживаемые поля `detail`:

- `level` (`success|info|warning|error|danger`)
- `title`
- `html` (или `message`/`text`)
- `imageUrl` (или `image`), `imageAlt`
- `timer` (мс)
- `position` (например `top-end`)


## Frontend Unit Tests

Тесты расположены в `develop/symfony/assets/tests/` — обычные `.mjs` файлы, запускаемые через `node` без каких-либо фреймворков.  
Используется встроенный модуль `node:assert/strict`.

### Запуск

```bash
bash develop/symfony/assets/tests.sh
```

### Структура

```
assets/tests/
  uploadHint.test.mjs    # formatBytes, buildUploadHint
```

### Как устроены тесты

Каждый файл — самостоятельный ES-модуль (`.mjs`):
- импортирует нужный модуль через относительный путь (`../home/...`)
- проверяет поведение через `assert`
- выводит `✓ <название>` для каждой пройденной группы
- падает с ненулевым exit-кодом при первом несоответствии

```js
import assert from 'node:assert/strict';
import { myFn } from '../home/path/to/module.js';

assert.equal(myFn(1), 2);
console.log('✓ myFn');
```

### Добавление нового теста

Создай файл `assets/tests/<name>.test.mjs` — скрипт `tests.sh` подхватит его автоматически.

