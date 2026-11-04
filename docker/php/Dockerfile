# syntax=docker/dockerfile:1.6
FROM composer:2 AS vendor

WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
# Cài cả dev vì phpunit chạy trong container (kiểm thử SRS 15.2).
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-interaction

FROM php:8.3-fpm-alpine

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev libpng-dev libzip-dev icu-dev sqlite-dev \
 && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql pdo_sqlite bcmath pcntl gd zip intl opcache \
 && apk del .build-deps \
 && apk add --no-cache postgresql-libs libpng libzip icu tzdata

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY backend/ .

RUN composer dump-autoload --optimize --no-scripts --no-interaction \
 && chown -R www-data:www-data storage bootstrap/cache

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
