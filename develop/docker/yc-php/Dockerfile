FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    bash \
    icu-dev \
    libpq-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-install \
    intl \
    pdo \
    pdo_pgsql \
    zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY symfony/ /var/www/yc/

WORKDIR /var/www/yc

RUN chown -R www-data:www-data /var/www/yc

EXPOSE 9000

CMD ["php-fpm"]
