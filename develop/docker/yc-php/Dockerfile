FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    bash \
    icu-dev \
    libpq-dev \
    libzip-dev \
    unzip \
    $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-install \
    intl \
    pdo \
    pdo_pgsql \
    zip \
    && apk del $PHPIZE_DEPS

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY symfony/ /var/www/yc/

WORKDIR /var/www/yc

ARG DEFAULT_URI
RUN composer install --no-interaction --prefer-dist --no-progress --optimize-autoloader

RUN chown -R www-data:www-data /var/www/yc

EXPOSE 9000

CMD ["php-fpm"]
