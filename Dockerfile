# FORCE_REBUILD_CACHE_FIX_v3
FROM php:8.2-apache

# Install system dependencies and PHP extensions needed for Laravel & MySQL
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    default-libmysqlclient-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql gd

# Enable Apache Rewrite Module
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy all project files first so artisan and everything else exist
COPY . .

# Install project dependencies with increased process timeout and prefer-dist to avoid timeouts
RUN composer config --global process-timeout 2000 \
    && composer install --no-dev --optimize-autoloader --prefer-dist

# Set permissions for Laravel storage and cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Expose port 80 for Render
ENV PORT=80
EXPOSE 80

# Configure Apache document root to point to Laravel's public folder
RUN sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf

# Start Apache & Migrate
CMD php artisan config:clear && php artisan migrate --force && apache2-foreground