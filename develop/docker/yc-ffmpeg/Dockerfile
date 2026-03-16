ARG PROJECT_VERSION=0.0.1

FROM olegmilantiev/yc-php:${PROJECT_VERSION}

RUN apk add --no-cache ffmpeg

CMD ["/var/www/yc/bin/console messenger:consume async"]
