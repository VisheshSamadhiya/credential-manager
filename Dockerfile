FROM php:8.3-apache

RUN docker-php-ext-install pdo pdo_mysql

WORKDIR /var/www

COPY . /var/www

RUN cp -r /var/www/public/* /var/www/html/

EXPOSE 80
