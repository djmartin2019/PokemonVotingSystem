#!/bin/bash

set -e

echo "Fetching latest..."
git fetch origin

echo "Checking out deploy branch..."
git checkout vps-migration

echo "Pulling latest changes..."
git pull origin vps-migration

echo "Rebuilding containers..."
docker compose build --pull

echo "Running database migrations..."
docker compose run --rm --no-deps web php /var/www/html/scripts/migrate.php

echo "Restarting services..."
docker compose up -d

echo "Building resized sprites (only missing ones; first run takes a few minutes)..."
docker compose exec -T -u www-data web php /var/www/html/scripts/warm_sprites.php || echo "Sprite warm-up failed; sprites will be built on first view instead."

echo "Deployment complete."
