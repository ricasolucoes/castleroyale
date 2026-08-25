# Project Dominion
#
# Docker is the canonical development environment — the host may lack
# pdo_pgsql, so anything database-shaped runs inside a container.

.DEFAULT_GOAL := help
COMPOSE := docker compose
API     := $(COMPOSE) exec -T api

.PHONY: help setup dev stop restart logs shell test test-arch test-postgres lint lint-fix \
        analyse migrate migrate-fresh seed reset health smoke gamedata contracts ci clean

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

setup: ## Build images, install dependencies, migrate and seed
	@test -f apps/api/.env || cp apps/api/.env.example apps/api/.env
	$(COMPOSE) build
	$(COMPOSE) up -d --wait postgres redis
	$(COMPOSE) run --rm api composer install
	$(COMPOSE) run --rm api php artisan key:generate --force
	npm install
	$(COMPOSE) up -d --wait
	$(MAKE) migrate
	$(MAKE) seed
	@$(MAKE) --no-print-directory health
	@echo ""
	@echo "  Ready.  make dev   ->  http://localhost:8080"

dev: ## Start the full stack and block until every service reports healthy
	@test -f apps/api/.env || cp apps/api/.env.example apps/api/.env
	$(COMPOSE) up -d --wait
	@$(MAKE) --no-print-directory health
	@echo ""
	@echo "  API         http://localhost:8080"
	@echo "  Health      http://localhost:8080/api/v1/health"
	@echo "  Back office http://localhost:8080/admin"
	@echo "  Horizon     http://localhost:8080/horizon"
	@echo "  Mailpit     http://localhost:8025"
	@echo "  MinIO       http://localhost:9001"

health: ## Assert /api/v1/health returns 200 with every dependency check true
	@out="$$($(COMPOSE) exec -T api curl -fsS http://localhost:8000/api/v1/health)" \
		|| { echo "  health: request failed (is the api container up?)"; exit 1; }; \
	 echo "$$out" | grep -q '"status":"ok"' \
		|| { echo "  health: degraded -> $$out"; exit 1; }; \
	 echo "  health: ok"

smoke: ## Prove the stack: every service healthy + the health endpoint ok
	./scripts/stack-smoke.sh

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

test-postgres: ## PostGIS-only tests against real PostgreSQL (phpunit.postgres.xml)
	@$(COMPOSE) exec -T postgres psql -U dominion -d dominion -tc \
		"SELECT 1 FROM pg_database WHERE datname='dominion_test'" | grep -q 1 \
		|| $(COMPOSE) exec -T postgres createdb -U dominion dominion_test
	$(API) ./vendor/bin/pest --configuration=phpunit.postgres.xml

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
	$(MAKE) test-postgres
	npm run typecheck
	npm test

clean: ## Remove containers and volumes (DESTROYS local data)
	$(COMPOSE) down -v
