#!/bin/bash
#
# Laravel Full Deployment Script
#
set -e

BRANCH=$1
PROJECT_PATH="/var/www/dev/syifa-web-admin-filament"
PHP_BIN="/usr/bin/php"
COMPOSER_BIN="/usr/bin/composer"
WORKER_SERVICE="laravel-queue"

echo "======================================"
echo "Branch: $BRANCH"
echo "Path:   $PROJECT_PATH"
echo "======================================"

cd $PROJECT_PATH || exit 1

echo "Pulling latest code from $BRANCH..."
git fetch --all
git reset --hard origin/$BRANCH

echo "Running composer install..."
$COMPOSER_BIN install --no-interaction --prefer-dist --optimize-autoloader || { echo "❌ Composer install failed"; exit 1; }

echo "Running migrations..."
$PHP_BIN artisan migrate --force || { echo "❌ Migration failed"; exit 1; }

echo "Optimizing Laravel..."
$PHP_BIN artisan optimize || { echo "❌ Optimize failed"; exit 1; }
$PHP_BIN artisan config:cache || { echo "❌ Config cache failed"; exit 1; }
$PHP_BIN artisan route:cache || { echo "❌ Route cache failed"; exit 1; }
$PHP_BIN artisan view:cache || { echo "❌ View cache failed"; exit 1; }

echo "Clearing cache..."
$PHP_BIN artisan optimize:clear || { echo "❌ Clear cache failed"; exit 1; }

echo "Restarting Supervisor services..."
supervisorctl reread || { echo "❌ Supervisor reread failed"; exit 1; }
supervisorctl update || { echo "❌ Supervisor update failed"; exit 1; }
supervisorctl restart $WORKER_SERVICE || { echo "❌ Worker restart failed"; exit 1; }

echo "🎉 Deployment Done!"
