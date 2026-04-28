FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    git unzip zip curl \
    libzip-dev libpng-dev libonig-dev libxml2-dev \
    libsqlite3-dev nodejs npm \
    && docker-php-ext-install pdo pdo_mysql pdo_sqlite mbstring zip exif pcntl

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

RUN npm install && npm run build

RUN php artisan package:discover --ansi || true

RUN mkdir -p database && touch database/database.sqlite
RUN php artisan migrate --force || true
RUN chmod -R 775 storage bootstrap/cache database

EXPOSE 10000

CMD php artisan serve --host=0.0.0.0 --port=${PORT:-10000}
