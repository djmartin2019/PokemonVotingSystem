#!/bin/bash

set -e

echo "Fetching latest..."
git fetch origin

echo "Checking out deploy branch..."
git checkout vps-migration

echo "Pulling latest changes..."
git pull origin vps-migration

echo "Rebuilding containers..."
docker compose build

echo "Restarting services..."
docker compose up -d

echo "Deployment complete."
