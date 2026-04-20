#!/bin/sh
set -e

# Read secrets from files and export as environment variables
read_secret() {
    var_name=$1
    file_path_var="${var_name}_FILE"
    file_path=$(eval echo "\$$file_path_var")
    
    if [ -n "$file_path" ] && [ -f "$file_path" ]; then
        value=$(cat "$file_path")
        export "$var_name"="$value"
        echo "Loaded secret: $var_name"
    elif [ -n "$file_path" ]; then
        echo "Warning: Secret file not found: $file_path"
    fi
}

# Load all secrets from _FILE environment variables
read_secret "APP_KEY"
read_secret "GEMINI_API_KEY"
read_secret "AWS_ACCESS_KEY_ID"
read_secret "AWS_SECRET_ACCESS_KEY"
read_secret "AWS_BUCKET"
read_secret "AWS_DEFAULT_REGION"

# Ensure database directory exists and is writable
mkdir -p /app/database
chown www-data:www-data /app/database

# Ensure storage directories exist and are writable
mkdir -p /app/storage/app/public
mkdir -p /app/storage/app/private
mkdir -p /app/storage/framework/cache
mkdir -p /app/storage/framework/sessions
mkdir -p /app/storage/framework/views
mkdir -p /app/storage/logs
chown -R www-data:www-data /app/storage
chmod -R 755 /app/storage

# Ensure bootstrap/cache is writable
chown -R www-data:www-data /app/bootstrap/cache
chmod -R 755 /app/bootstrap/cache

# Create SQLite database file if it doesn't exist
if [ ! -f "/app/database/database.sqlite" ]; then
    touch /app/database/database.sqlite
    chown www-data:www-data /app/database/database.sqlite
fi

# Run migrations on app service only
if [ "$1" = "app" ]; then
    echo "Running migrations..."
    php artisan migrate --force
    
    # Create storage link if needed
    php artisan storage:link --force 2>/dev/null || true
fi

# Execute the appropriate command
case "$1" in
    app)
        echo "Starting FrankenPHP..."
        frankenphp run --config /etc/caddy/Caddyfile
        ;;
    scheduler)
        echo "Starting scheduler..."
        php artisan schedule:work
        ;;
    worker)
        echo "Starting queue worker..."
        php artisan queue:work --queue=rss,enrichment,logos,cleanup --sleep=3 --tries=3
        ;;
    *)
        exec "$@"
        ;;
esac
