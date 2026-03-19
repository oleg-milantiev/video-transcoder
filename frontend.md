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

