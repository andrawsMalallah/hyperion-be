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

# Delete dead Passport tokens (ROADMAP 7.5). Every login mints a token and every
# logout revokes one, but nothing ever removed the dead rows — and the free tier
# has no scheduler, so this deploy hook is the only place a purge can run.
# Only touches tokens that are already useless: revoked ones, and expired ones
# past the 7-day retention default. Live sessions are untouched.
echo "Purging revoked and expired tokens..."
php artisan passport:purge

# Cache routes and config for production performance
echo "Caching configuration and routes..."
php artisan optimize

# Start supervisor to run Nginx and PHP-FPM
echo "Starting supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
