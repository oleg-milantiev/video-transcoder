# Используем свежий PHP 8.4 на базе Alpine
FROM php:8.4-fpm-alpine

# Устанавливаем системные зависимости для расширений PHP и сам Composer
RUN apk add --no-cache \
    bash \
    git \
    icu-dev \
    libpq-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-install \
    intl \
    pdo \
    pdo_pgsql \
    zip

# Копируем Composer из официального образа
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Настраиваем рабочую директорию
WORKDIR /var/www/yc

# Создаем пустой проект Symfony (скелет) прямо в папке
# Флаг --no-interaction нужен для автоматизации сборки
RUN composer create-project symfony/skeleton . --no-interaction

# Устанавливаем аннотации и веб-сервер (пакеты, нужные для работы index страницы)
RUN composer require symfony/webapp-pack --no-interaction

# Выставляем права на папки логов и кэша (важно для Alpine)
RUN chown -R www-data:www-data /var/www/yc

# Открываем порт для FPM
EXPOSE 9000

CMD ["php-fpm"]
