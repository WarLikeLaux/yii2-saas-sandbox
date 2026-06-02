FROM php:7.4-fpm

# Системные библиотеки, нужные для сборки PHP-расширений
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
        pgsql \
        intl \
        zip \
        gd \
        mbstring \
        bcmath \
        exif \
        sockets \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# По умолчанию php-fpm очищает окружение воркеров (clear_env = yes),
# из-за чего getenv() в веб-запросах не видит переменные из docker-compose.
# Отключаем, чтобы DB_HOST/REDIS_HOST и т.п. долетали до приложения.
RUN echo "clear_env = no" >> /usr/local/etc/php-fpm.d/www.conf

# Composer из официального образа
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
