#!/bin/bash
set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}Starting migration process...${NC}"

# Set force flag for production
FORCE_FLAG=""
if [ "${APP_ENV}" = "production" ]; then
    FORCE_FLAG="--force"
fi

# Set seed flag for non-production
SEED_FLAG=""
if [ "${RUN_SEEDERS}" = "true" ] && [ "${APP_ENV}" != "production" ]; then
    SEED_FLAG="--seed"
fi

# Run the safe migration command
php artisan migrate:safe $FORCE_FLAG $SEED_FLAG

echo -e "${GREEN}Migration process finished${NC}"