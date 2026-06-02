FROM php:7.4-fpm

ARG UID=1000
ARG GID=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libicu-dev \
        libzip-dev \
        libonig-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        intl \
        zip \
        gd \
        mbstring \
        bcmath \
        sockets \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN groupadd -g ${GID} app \
    && useradd -u ${UID} -g ${GID} -m app \
    && echo "clear_env = no" >> /usr/local/etc/php-fpm.d/www.conf

ENV HOME=/home/app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
