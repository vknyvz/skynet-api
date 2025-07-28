FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    unzip \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libmcrypt-dev \
    netcat-openbsd \
    libicu-dev \
    supervisor \
    nginx \
    librabbitmq-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
        sockets

# Install Redis and AMQP extensions
RUN pecl install redis amqp && docker-php-ext-enable redis amqp

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy *all* application code first
COPY . .

# Remove any existing vendor directory and install dependencies
# Use --no-scripts here to prevent automatic script execution during install,
# especially if those scripts rely on a fully built application (like bin/console).
RUN rm -rf vendor/ && composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Create necessary directories and set permissions
RUN mkdir -p var/cache var/log var/sessions \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/var \
    && chmod -R 775 var/cache var/log var/sessions

# Copy nginx configuration
COPY docker/nginx/default.conf /etc/nginx/sites-available/default

# Copy supervisord configuration
COPY docker/supervisord/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Now that the application is fully copied and dependencies are installed, clear cache.
# This should be done *after* all application files are in place.
RUN php bin/console cache:clear --env=prod

# Expose port
EXPOSE 8000

# Start supervisord
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]