ARG PHP_VERSION=8.5.12

FROM composer:2 AS composer

FROM php:${PHP_VERSION}-fpm-alpine

ENV PHP_XDEBUG_MODE='off'

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY php/php.ini /usr/local/etc/php/php.ini
COPY php/xdebug.ini /usr/local/etc/php/conf.d/

RUN apk add --no-cache bash git icu-libs libzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev linux-headers \
    && docker-php-ext-install -j"$(nproc)" intl pdo_mysql zip \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del .build-deps \
    && rm -rf /tmp/pear

WORKDIR /var/www
