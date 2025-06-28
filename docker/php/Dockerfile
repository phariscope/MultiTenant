FROM php:7.4-fpm

RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    libmariadb-dev \
    && docker-php-ext-install pdo pdo_sqlite pdo_mysql

# Installation de l'extension pecl pcov
RUN pecl install pcov \
    && docker-php-ext-enable pcov