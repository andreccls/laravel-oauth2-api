# Everything runs in containers: the host needs only Docker + make (no PHP, no Composer).
-include .env
export

DC    := docker compose
TOOLS := $(DC) --profile tools run --rm --no-deps tools       # no DB/Redis needed (lint, unit-ish)
TOOLS_WITH_DEPS := $(DC) --profile tools run --rm tools         # also starts MySQL/Redis (used by test-mysql)
API_PORT ?= 8088
FPM_PORT ?= 8089
COVERAGE_MIN ?= 100

.DEFAULT_GOAL := help
.PHONY: help build deps test test-mysql lint fmt coverage openapi up down demo bench keys clean

help: ## Show this help
	@grep -E '^[a-z-]+:.*##' $(MAKEFILE_LIST) | awk -F':.*## ' '{printf "  make %-11s %s\n", $$1, $$2}'

build: ## Build the Docker images (dev tooling + Octane runtime)
	$(DC) --profile tools build tools
	$(DC) build api

deps: ## Install Composer dependencies into the vendor volume
	$(TOOLS) composer install --no-interaction --prefer-dist

test: deps ## Run ALL tests (SQLite in memory: fast, no services needed)
	$(TOOLS) vendor/bin/phpunit

test-mysql: deps ## Run the same suite against a real MySQL 8.4 (database oauth2_test)
	$(DC) up -d --wait mysql
	$(DC) exec -T mysql mysql -uroot -pdev_only_root_password -e 'CREATE DATABASE IF NOT EXISTS oauth2_test'
	$(DC) --profile tools run --rm -e DB_CONNECTION=mysql -e DB_HOST=mysql -e DB_PORT=3306 -e DB_DATABASE=oauth2_test \
	  -e DB_USERNAME=root -e DB_PASSWORD=dev_only_root_password tools vendor/bin/phpunit

lint: deps ## Laravel Pint (style) + Larastan/PHPStan level 8
	$(TOOLS) vendor/bin/pint --test
	$(TOOLS) vendor/bin/phpstan analyse --memory-limit=1G --no-progress

fmt: deps ## Fix code style with Pint
	$(TOOLS) vendor/bin/pint

coverage: deps ## Tests + line coverage (PCOV). FAILS below COVERAGE_MIN (default 100). HTML in coverage/html
	$(TOOLS) sh -c 'rm -rf coverage && vendor/bin/phpunit --coverage-clover=coverage/clover.xml --coverage-html=coverage/html --coverage-text=coverage/summary.txt && cat coverage/summary.txt && php scripts/coverage-gate.php coverage/clover.xml $(COVERAGE_MIN)'

openapi: deps ## Regenerate docs/openapi.json (Scramble); the test suite fails if it is stale
	$(TOOLS) sh -c 'UPDATE_OPENAPI=1 vendor/bin/phpunit --filter OpenApiTest'

up: ## Start Octane API + MySQL + Redis + scheduler -> http://localhost:$(API_PORT)
	$(DC) up -d --build --wait api
	$(DC) up -d scheduler

down: ## Stop everything (keeps volumes)
	$(DC) --profile tools --profile bench down

demo: up ## Seed the demo user and run the full OAuth2 walkthrough with curl (scripts/demo.sh)
	$(DC) exec -T api php artisan db:seed --force --no-interaction
	API=http://localhost:$(API_PORT) COMPOSE="$(DC)" ./scripts/demo.sh

bench: up ## Benchmark php-fpm vs Octane on a protected endpoint (scripts/bench.sh)
	API_PORT=$(API_PORT) FPM_PORT=$(FPM_PORT) ./scripts/bench.sh

keys: ## (Re)generate Passport keys inside the running api container
	$(DC) exec -T api php artisan passport:keys --force

clean: ## Remove containers, volumes, images and build output created by this project
	$(DC) --profile tools --profile bench down -v --remove-orphans
	-docker image rm laravel-oauth2-api:octane laravel-oauth2-api:fpm laravel-oauth2-api:dev
	rm -rf coverage .phpunit.cache
