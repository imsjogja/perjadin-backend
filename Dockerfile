FROM composer:2 AS composer

FROM php:8.2-fpm-alpine AS base
WORKDIR /var/www

RUN apk add --no-cache font-carlito icu-dev libpng-dev libzip-dev oniguruma-dev sqlite-dev \
    && docker-php-ext-install bcmath gd intl mbstring pdo_mysql pdo_sqlite zip \
    && rm -rf /tmp/*

FROM base AS dependencies

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts

FROM base

COPY --from=dependencies /var/www/vendor ./vendor
COPY . .

RUN mkdir -p resources/fonts/carlito \
    && cp /usr/share/fonts/carlito/Carlito-Regular.ttf resources/fonts/carlito/ \
    && cp /usr/share/fonts/carlito/Carlito-Bold.ttf resources/fonts/carlito/ \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]
