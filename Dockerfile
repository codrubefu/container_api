FROM php:8.4-fpm-alpine

RUN apk add --no-cache libzip-dev icu-dev oniguruma-dev \
    && docker-php-ext-install pdo_mysql bcmath zip intl opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ARG UID=1000
ARG GID=1000
RUN apk add --no-cache shadow \
    && usermod -u ${UID} www-data && groupmod -g ${GID} www-data

WORKDIR /var/www/html
USER www-data
