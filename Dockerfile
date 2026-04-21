# Production Dockerfile for Laravel 13 API
# Uses FrankenPHP with PHP 8.4

FROM dunglas/frankenphp:1-php8.4-alpine

# Install system dependencies
RUN apk add --no-cache \
    curl \
    git \
    postgresql-dev \
    unzip \
    libzip-dev \
    oniguruma-dev \
    libxml2-dev \
    sqlite \
    sqlite-dev \
    && rm -rf /var/cache/apk/*
    
RUN docker-php-ext-install pdo pdo_pgsql

# Install PHP extensions
RUN install-php-extensions \
    pdo \
    pcntl \
    pdo_pgsql \
    mbstring \
    xml \
    ctype \
    json \
    tokenizer \
    bcmath \
    curl \
    fileinfo \
    openssl \
    zip \
    intl \
    opcache

# Configure PHP for production
RUN { \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.max_accelerated_files=20000'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'opcache.fast_shutdown=1'; \
    echo 'opcache.enable_cli=1'; \
    echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/opcache-recommended.ini

# Set working directory
WORKDIR /app

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy all application code (includes composer.json for install)
COPY --chown=www-data:www-data . .

# Install PHP dependencies (includes post-autoload scripts)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Set proper permissions
RUN chown -R www-data:www-data /app \
    && chmod -R 755 /app/storage \
    && chmod -R 755 /app/bootstrap/cache \
    && mkdir -p database && touch database/database.sqlite \
    && chown www-data:www-data database/database.sqlite \
    && chmod 755 database/database.sqlite


ENV SERVER_NAME=":80"

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
    CMD curl -f http://localhost:80/health || exit 1

# Expose port
EXPOSE 80

CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=80", "--admin-port=2019"]
