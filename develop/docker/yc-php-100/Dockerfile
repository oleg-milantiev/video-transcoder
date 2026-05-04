FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache \
    bash

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/100

# -----------------------------------
FROM base AS dev

RUN apk add --no-cache $PHPIZE_DEPS linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del $PHPIZE_DEPS linux-headers

RUN echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

EXPOSE 9000
CMD ["php-fpm"]


# ----------------------------
FROM base AS prod

# disable php-fpm logs. Use only symfony json logs on prod
COPY ./docker/yc-php-100/docker.conf /usr/local/etc/php-fpm.d/docker.conf

COPY 100percents/ /var/www/100/

ENV APP_ENV=prod
RUN composer install --no-interaction --prefer-dist --no-progress --optimize-autoloader --no-dev
RUN php bin/console asset-map:compile

RUN chown -R www-data:www-data /var/www/100
EXPOSE 9000
CMD ["php-fpm"]
