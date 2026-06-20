#!/bin/sh

# Replace Nginx port with Render's dynamic PORT environment variable (default to 80 if not set)
if [ -n "$PORT" ]; then
    echo "Configuring Nginx to listen on port $PORT"
    sed -i "s/listen 80;/listen ${PORT};/g" /etc/nginx/nginx.conf
    sed -i "s/listen \[::\]:80;/listen [::]:${PORT};/g" /etc/nginx/nginx.conf
fi

# Run database migrations on deploy
echo "Running migrations..."
php artisan migrate --force

# Cache routes and config for production performance
echo "Caching configuration and routes..."
php artisan optimize

# Start supervisor to run Nginx and PHP-FPM
echo "Starting supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
