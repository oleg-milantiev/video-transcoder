ARG PROJECT_VERSION=latest
FROM olegmilantiev/yc-php:${PROJECT_VERSION} AS php_source
FROM olegmilantiev/yc-php-100:${PROJECT_VERSION} AS php100_source

FROM nginx:1.28-alpine

COPY default.conf /etc/nginx/conf.d/default.conf

WORKDIR /var/www
COPY --from=php_source /var/www/yc/public ./yc/public
COPY --from=php100_source /var/www/100/public ./100/public

EXPOSE 80 443

CMD ["nginx", "-g", "daemon off;"]
