FROM php:8.2-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libpng-dev \
        libonig-dev \
        libxml2-dev \
        libcurl4-openssl-dev \
        pkg-config \
        libssl-dev \
        libicu-dev \
        g++ \
    && docker-php-ext-install pdo_mysql zip intl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json ./
RUN composer install --no-dev --prefer-dist --no-interaction

COPY . .

RUN composer dump-autoload --optimize

ENV LOG_DIR=/var/www/html/storage/logs \
    WHATSAPP_SESSION_DIR_BASE=/var/www/html/storage/sessions

RUN mkdir -p "$LOG_DIR" "$WHATSAPP_SESSION_DIR_BASE"

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
