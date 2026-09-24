FROM composer:2 AS dependencies

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader

FROM php:8.3-apache

RUN docker-php-ext-install pdo pdo_mysql

WORKDIR /var/www

COPY --from=dependencies /app/vendor /var/www/vendor

COPY . /var/www

RUN cp -r /var/www/public/* /var/www/html/

EXPOSE 80
