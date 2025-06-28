FROM php:7.4-cli

# Installer dépendances pour Composer
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    && rm -rf /var/lib/apt/lists/*

# Installer Composer (version 2.x recommandée pour PHP 7.4)
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app
