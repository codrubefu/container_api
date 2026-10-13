FROM php:8.4-fpm-alpine

RUN apk add --no-cache libzip-dev icu-dev oniguruma-dev \
    && docker-php-ext-install pdo_mysql bcmath zip intl opcache

# Microsoft ODBC driver + pdo_sqlsrv for SQL Server
RUN apk add --no-cache unixodbc unixodbc-dev curl gnupg $PHPIZE_DEPS \
    && curl -fsSLO https://download.microsoft.com/download/7/6/d/76de322a-d860-4894-9945-f0cc5d6a45f8/msodbcsql18_18.4.1.1-1_amd64.apk \
    && apk add --no-cache --allow-untrusted msodbcsql18_18.4.1.1-1_amd64.apk \
    && rm msodbcsql18_18.4.1.1-1_amd64.apk \
    && pecl install sqlsrv pdo_sqlsrv \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv \
    && apk del $PHPIZE_DEPS unixodbc-dev gnupg

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ARG UID=1000
ARG GID=1000
RUN apk add --no-cache shadow \
    && usermod -u ${UID} www-data && groupmod -g ${GID} www-data

WORKDIR /var/www/html
USER www-data
