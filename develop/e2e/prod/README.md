# Prod-safe Playwright smoke

Отдельный изолированный suite для ежедневного запуска против production / release окружения.

## Что делает

Сценарий в `tests/01.prod.safe.js`:

1. логинится админом;
2. создаёт изолированного пользователя `prod-YYYYMMDD@example.test` с ролью `ROLE_USER` и тарифом `Free`;
3. логинится этим пользователем;
4. проверяет пустые вкладки `Upload`, `Videos`, `Tasks`;
5. загружает `2022_10_04_Two_Maxes.mp4` под именем `prod-YYYYMMDD.mp4`;
6. проверяет карточку видео, постер, `meta.duration` и обязательные пресеты;
7. проверяет cancel / pending сценарий на `Free`;
8. меняет тариф пользователя на `Premium` через админку c фильтром по email;
9. запускает три обязательных пресета, дожидается `Completed`, скачивает результаты и проверяет имена файлов;
10. удаляет видео;
11. удаляет тестового пользователя.

## Артефакты

Suite сохраняет:

- скриншоты по этапам;
- Playwright trace;
- browser video;
- `browser-console.log`;
- `mercure-sse.json`.

## Именование

По умолчанию используются:

- пользователь: `prod-YYYYMMDD@example.test`
- видео: `prod-YYYYMMDD.mp4`
- пароль: случайный 8-символьный alnum

Переопределяется через переменные окружения:

- `PROD_DATE_SUFFIX`
- `PROD_USER_LOCAL_PART`
- `PROD_USER_DOMAIN`
- `PROD_USER_EMAIL`
- `PROD_USER_PASSWORD`
- `PROD_VIDEO_BASENAME`
- `PROD_SOURCE_VIDEO`

## Запуск

Из host-машины, когда prod stack поднят через `develop/docker-compose.prod.yml`:

```bash
cd /root/video-transcoder/develop/e2e
bash prod/run.prod.sh
```

С переопределением admin credentials:

```bash
cd /root/video-transcoder/develop/e2e
ADMIN_EMAIL="admin@example.com" ADMIN_PASSWORD="secret" bash prod/run.prod.sh
```

С уже заданным паролем тестового пользователя:

```bash
cd /root/video-transcoder/develop/e2e
PROD_USER_PASSWORD="Abc12345" bash prod/run.prod.sh
```

Runner сам заходит в `playwright`-контейнер через `docker compose -f develop/docker-compose.prod.yml exec -T playwright ...`.

## Безопасные проверки без запуска теста

```bash
cd /root/video-transcoder/develop
docker compose -f docker-compose.prod.yml config >/dev/null

cd /root/video-transcoder/develop/e2e
bash -n prod/run.prod.sh
node --check prod/playwright.config.js
node --check prod/helpers/runContext.js
node --check prod/helpers/admin.js
node --check prod/helpers/index.js
node --check prod/tests/01.prod.safe.js
```

## Важные замечания

- Suite не зависит от `tests/01-08` и не использует их data dependencies.
- Для устойчивости к текущему состоянию проекта прод-тест принимает alias-имена пресетов:
  - `180p`
  - `HD, 3Mbps`
  - `Full HD, 6Mbps`
- Текущий frontend рендерит статусы задач в uppercase (`PENDING`, `PROCESSING`, `CANCELLED`, `COMPLETED`), поэтому assertions нормализуют регистр.

