ARG PROJECT_VERSION=latest
FROM olegmilantiev/yc-php:${PROJECT_VERSION} AS php_source

FROM nginx:1.28-alpine

COPY default.conf /etc/nginx/conf.d/default.conf

WORKDIR /var/www/yc
COPY --from=php_source /var/www/yc/public ./public

EXPOSE 80 443

CMD ["nginx", "-g", "daemon off;"]
