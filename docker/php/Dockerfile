FROM php:8.3.11-fpm

RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    libmariadb-dev \
    && docker-php-ext-install pdo pdo_sqlite pdo_mysql

RUN pecl install pcov && docker-php-ext-enable pcov