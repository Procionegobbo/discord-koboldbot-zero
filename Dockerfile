FROM php:8.4-cli-alpine

RUN apk add --no-cache \
    libsodium-dev \
    && docker-php-ext-install \
        pcntl \
        sodium \
    && docker-php-ext-enable \
        pcntl \
        sodium

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

COPY . .

CMD ["php", "application", "bot:run"]
