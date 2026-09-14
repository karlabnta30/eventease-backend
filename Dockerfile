FROM php:8.2-fpm

RUN apt-get update && apt-get install -y libpng-dev libjpeg-dev libfreetype6-dev zip unzip git curl libpq-dev
RUN docker-php-ext-install pdo pdo_pgsql gd
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader

EXPOSE 10000
CMD php artisan serve --host=0.0.0.0 --port=10000
