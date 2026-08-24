# Project Dominion
#
# Docker is the canonical development environment — the host may lack
# pdo_pgsql, so anything database-shaped runs inside a container.

.DEFAULT_GOAL := help
COMPOSE := docker compose
API     := $(COMPOSE) exec -T api

.PHONY: help setup dev stop restart logs shell test test-arch lint lint-fix \
        analyse migrate migrate-fresh seed reset gamedata contracts ci clean

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

setup: ## Build images, install dependencies, migrate and seed
	@test -f apps/api/.env || cp apps/api/.env.example apps/api/.env
	$(COMPOSE) build
	$(COMPOSE) up -d postgres redis
	$(COMPOSE) run --rm api composer install
	$(COMPOSE) run --rm api php artisan key:generate --force
	npm install
	$(MAKE) migrate
	$(MAKE) seed
	@echo ""
	@echo "  Ready.  make dev   ->  http://localhost:8080"

dev: ## Start the full stack
	$(COMPOSE) up -d
	@echo ""
	@echo "  API         http://localhost:8080"
	@echo "  Health      http://localhost:8080/api/v1/health"
	@echo "  Back office http://localhost:8080/admin"
	@echo "  Horizon     http://localhost:8080/horizon"
	@echo "  Mailpit     http://localhost:8025"

stop: ## Stop the stack
	$(COMPOSE) down

restart: stop dev ## Restart the stack

logs: ## Tail logs (S=service to narrow)
	$(COMPOSE) logs -f $(S)

shell: ## Shell into the api container
	$(COMPOSE) exec api sh

migrate: ## Run migrations against PostgreSQL + PostGIS
	$(API) php artisan migrate --force

migrate-fresh: ## Drop everything and re-migrate
	$(API) php artisan migrate:fresh --force

seed: ## Seed development data
	$(API) php artisan db:seed --force

reset: migrate-fresh seed ## Fresh database with seeds

test: ## Backend test suite
	$(API) ./vendor/bin/pest

test-arch: ## Architecture rules only
	$(API) ./vendor/bin/pest --group=arch

lint: ## Check formatting
	$(API) ./vendor/bin/pint --test
	npm run lint

lint-fix: ## Fix formatting
	$(API) ./vendor/bin/pint
	npm run lint:fix

analyse: ## Static analysis (PHPStan level 8 + strict rules)
	$(API) ./vendor/bin/phpstan analyse --memory-limit=1G

gamedata: ## Validate and import game data
	npm run gamedata:validate
	$(API) php artisan game:import-data

contracts: ## Regenerate TypeScript types from the OpenAPI spec
	npm run contracts:generate

ci: lint analyse test ## Everything CI runs
	npm run typecheck
	npm test

clean: ## Remove containers and volumes (DESTROYS local data)
	$(COMPOSE) down -v
