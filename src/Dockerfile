FROM php:8.4-fpm

ARG APP_UID=1000

RUN apt-get update && apt-get install -y \
    git \
    openssh-client \
    unzip \
    curl \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Make the PHP runtime user match the host user's UID.
# WSL normally uses 1000; macOS commonly uses 501.
# Keep an internal container GID of 1000 rather than attempting to use
# the macOS GID (e.g. 20), which may already exist in Debian.
RUN groupmod -g 1000 www-data \
    && usermod -u "${APP_UID}" -d /home/www-data -s /bin/bash www-data \
    && mkdir -p /home/www-data/.composer /home/www-data/.ssh \
    && chown -R www-data:www-data /home/www-data

WORKDIR /var/www/html
