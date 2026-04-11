# Необходимо разработать prod/relase.sh скрипт

## Общие правила

- установить режим выхода из скрипта по ошибке
- в начале работы скрипта нужно установить maintenance режим созданием пустого файла ./maintenance/enable
- в конце работы скрипта нужно этот файл удалить
- вывести итоговую информацию на английском

## Входные данные

- *текущая версия продукта* находится по тегу сейчас запущенного контейнера командой "docker compose ps php --format json | jq .Image". Например: "olegmilantiev/yc-php:0.1.1" - версия 0.1.1
- *новая версия продукта* - это версия, которая будет запущена после релиза. Задаётся в .env переменной PROJECT_VERSION

## Режимы работы

### Релиз

Если новая версия больше текущей, например 0.1.0 -- 0.1.1, включается режим релиза новой версии.

- docker compose pull, чтобы скачать новые версии;
- docker compose stop php ffmpeg ffmpeg-transcode
- docker compose up -d php ffmpeg ffmpeg-transcode
- docker compose exec php php bin/console doct:migr:migr
- docker compose exec php php bin/console cache:clear
- docker compose exec php php bin/console app:smoke:prod
- docker compose stop nginx
- docker compose up -d nginx

### Rollback

Если новая версия меньше текущей, например 0.1.1 -- 0.1.0, включается режим отката неудачного релиза. 

- поднять php контейнер с новой версией (0.1.0), не останавливая старый (0.1.1) отдельно
- с сортировкой по имени файла, найти последний в папке migrations этой версии приложения. Например это Version20260409120000.php
- остановить php контейнер с новой версией (0.1.0)
- в текущем php контейнере (0.1.1) выполнить команду отката миграции до найденной ранее: php bin/console doct:migr:migr DoctrineMigrations\\Version20260409120000 --no-interaction
- docker compose stop php ffmpeg ffmpeg-transcode
- docker compose up -d php ffmpeg ffmpeg-transcode
- docker compose exec php php bin/console cache:clear
- docker compose exec php php bin/console app:smoke:prod
- docker compose stop nginx
- docker compose up -d nginx
