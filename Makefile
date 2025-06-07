# PaperPulse Docker Makefile
.PHONY: help build up down restart logs shell clean

# Default target
help:
	@echo "PaperPulse Docker Commands:"
	@echo "  make build       - Build all Docker images"
	@echo "  make up          - Start all services in production mode"
	@echo "  make up-dev      - Start all services in development mode"
	@echo "  make down        - Stop all services"
	@echo "  make restart     - Restart all services"
	@echo "  make logs        - View logs from all services"
	@echo "  make shell       - Open shell in web container"
	@echo "  make horizon     - View Horizon dashboard logs"
	@echo "  make migrate     - Run database migrations"
	@echo "  make fresh       - Fresh migration with seeders"
	@echo "  make test        - Run tests"
	@echo "  make clean       - Remove all containers and volumes"

# Build all images
build:
	docker-compose build --no-cache

# Start services in production mode
up:
	docker-compose up -d
	@echo "PaperPulse is starting..."
	@echo "Web UI will be available at http://localhost"
	@echo "Run 'make logs' to view logs"

# Start services in development mode
up-dev:
	docker-compose -f docker-compose.yml -f docker-compose.dev.yml up -d
	@echo "PaperPulse (DEV) is starting..."
	@echo "Web UI: http://localhost"
	@echo "Mailpit: http://localhost:8025"
	@echo "Run 'make logs' to view logs"

# Stop all services
down:
	docker-compose down

# Restart all services
restart: down up

# View logs
logs:
	docker-compose logs -f

# View specific service logs
logs-web:
	docker-compose logs -f web

logs-worker:
	docker-compose logs -f worker

logs-scheduler:
	docker-compose logs -f scheduler

# Shell access
shell:
	docker-compose exec web bash

shell-worker:
	docker-compose exec worker bash

# Database operations
migrate:
	docker-compose exec web php artisan migrate

fresh:
	docker-compose exec web php artisan migrate:fresh --seed

# Queue operations
horizon:
	docker-compose exec worker php artisan horizon:status

horizon-clear:
	docker-compose exec worker php artisan horizon:clear

# Cache operations
cache-clear:
	docker-compose exec web php artisan cache:clear
	docker-compose exec web php artisan config:clear
	docker-compose exec web php artisan route:clear
	docker-compose exec web php artisan view:clear

optimize:
	docker-compose exec web php artisan config:cache
	docker-compose exec web php artisan route:cache
	docker-compose exec web php artisan view:cache

# Testing
test:
	docker-compose exec web php artisan test

test-coverage:
	docker-compose exec web php artisan test --coverage

# Maintenance
clean:
	docker-compose down -v
	@echo "All containers and volumes have been removed"

# Production deployment
deploy:
	git pull origin main
	make build
	make up
	make migrate
	make optimize
	@echo "Deployment complete!"