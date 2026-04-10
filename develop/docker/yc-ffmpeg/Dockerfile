ARG PROJECT_VERSION=0.0.1

FROM olegmilantiev/yc-php:${PROJECT_VERSION} AS base

COPY zz-memory-limit.ini /usr/local/etc/php/conf.d/zz-memory-limit.ini

RUN apk add --no-cache ffmpeg

CMD ["/var/www/yc/bin/console messenger:consume async"]

#----------------------

FROM base AS dev

RUN apk add --no-cache $PHPIZE_DEPS linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del $PHPIZE_DEPS linux-headers

RUN echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

#----------------------

FROM base AS prod

