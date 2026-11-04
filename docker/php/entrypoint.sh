#!/bin/sh
set -e

cd /var/www/html

# Wait for PostgreSQL to accept connections before touching the database.
if [ -n "$DB_HOST" ]; then
  until php -r 'try { new PDO("pgsql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); exit(0); } catch (Throwable $e) { exit(1); }'; do
    echo "waiting for postgres at $DB_HOST:$DB_PORT"
    sleep 2
  done
fi

STORAGE_READY=/var/www/html/storage/.docker-ready
if [ ! -f "$STORAGE_READY" ]; then
  mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
  chown -R www-data:www-data storage bootstrap/cache
  touch "$STORAGE_READY"
fi

if [ "$RUN_MIGRATIONS" = "true" ]; then
  php artisan migrate --force --no-interaction
  php artisan db:seed --force --no-interaction || true
  php artisan storage:link --force 2>/dev/null || true
fi

exec "$@"
