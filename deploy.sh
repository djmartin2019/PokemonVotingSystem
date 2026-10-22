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

echo "Deployment complete."
