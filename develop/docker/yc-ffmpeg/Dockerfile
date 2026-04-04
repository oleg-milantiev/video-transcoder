ARG PROJECT_VERSION=0.0.1

FROM olegmilantiev/yc-php:${PROJECT_VERSION} AS base

COPY ./php/zz-memory-limit.ini /usr/local/etc/php/conf.d/zz-memory-limit.ini

RUN apk add --no-cache ffmpeg

CMD ["/var/www/yc/bin/console messenger:consume async"]


#----------------------

FROM base AS dev


#----------------------

FROM base AS prod

