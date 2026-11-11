
USER := $(shell id -u)
GROUP := $(shell id -g)
XDEBUG_MODE ?= develop,debug,coverage
DOCKER_COMPOSE := docker compose

.DEFAULT_GOAL:=help

##@ Help

.PHONY: help

help: ## Show this help screen.
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make \033[36m<target>\033[0m\n"} /^[a-zA-Z0-9_-]+:.*?##/ { printf "  \033[36m%-25s\033[0m %s\n", $$1, $$2 } /^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) } ' $(MAKEFILE_LIST)

##@ Build
.PHONY: setup-host start stop down destroy first-run

setup-host: ## Add the project hostname to /etc/hosts and create its local TLS certificate
	@./etc/infrastructure/setup-hostname.sh
	@./etc/infrastructure/setup-ssl.sh

start: ## Build and start the containers
	@make hooks
	@export USER_ID=$(USER); \
	export GROUP_ID=$(GROUP); \
	export XDEBUG_MODE=$(XDEBUG_MODE); \
	$(DOCKER_COMPOSE) up -d --build --remove-orphans

stop: ## Stop the containers
	@-$(DOCKER_COMPOSE) stop

down: ## Stop and remove the containers
	@-$(DOCKER_COMPOSE) down

destroy: ## Remove the containers and their volumes (the database is lost)
	@-$(DOCKER_COMPOSE) down -v

first-run: ## Create .env, set up the host and start the containers
	@-cp -n .env.dist .env
	@make setup-host
	@make start
	@make install

##@ Composer
.PHONY: install update require require-dev

install: ## Install the dependencies from composer.lock
	@-$(DOCKER_COMPOSE) exec php-fpm composer install $(filter-out $@,$(MAKECMDGOALS))

update: ## Update the dependencies within the composer.json constraints
	@-$(DOCKER_COMPOSE) exec php-fpm composer update $(filter-out $@,$(MAKECMDGOALS))

require: ## Add a dependency
	@-$(DOCKER_COMPOSE) exec php-fpm composer require $(filter-out $@,$(MAKECMDGOALS))

require-dev: ## Add a development dependency
	@-$(DOCKER_COMPOSE) exec php-fpm composer require --dev $(filter-out $@,$(MAKECMDGOALS))

##@ Development
.PHONY: run run-debug logs status shell db-shell hooks

run: ## Run a Symfony console command
	@-$(DOCKER_COMPOSE) exec php-fpm bin/console $(filter-out $@,$(MAKECMDGOALS))

run-debug: ## Run a Symfony console command with Xdebug connecting to the IDE
	@-$(DOCKER_COMPOSE) exec -e PHP_XDEBUG_MODE=debug php-fpm bin/console $(filter-out $@,$(MAKECMDGOALS))

logs: ## Show the containers logs
	@-$(DOCKER_COMPOSE) logs $(filter-out $@,$(MAKECMDGOALS))

status: ## Show the containers status
	@-$(DOCKER_COMPOSE) ps

shell: ## Open a shell in the PHP container
	@-$(DOCKER_COMPOSE) exec php-fpm /bin/bash

db-shell: ## Open a MySQL shell on the dev database
	@-$(DOCKER_COMPOSE) exec mysql mysql -uapp -papp app

hooks: ## Install the git hooks (they run inside the PHP image)
	@rm -rf .git/hooks
	@ln -s ../etc/git/hooks-docker .git/hooks

##@ Quality
.PHONY: test coverage phpstan deptrac cs-fixer code-check

test: ## Run the unit tests
	@$(DOCKER_COMPOSE) exec php-fpm vendor/bin/phpunit --testdox $(filter-out $@,$(MAKECMDGOALS))

coverage: ## Run the unit tests with an HTML coverage report in var/coverage
	@$(DOCKER_COMPOSE) exec -e PHP_XDEBUG_MODE=coverage php-fpm vendor/bin/phpunit --coverage-html var/coverage $(filter-out $@,$(MAKECMDGOALS))

phpstan: ## Run the static analysis
	@$(DOCKER_COMPOSE) exec php-fpm vendor/bin/phpstan analyse

deptrac: ## Check the dependencies between layers
	@$(DOCKER_COMPOSE) exec php-fpm vendor/bin/deptrac

cs-fixer: ## Fix the coding standard
	@$(DOCKER_COMPOSE) exec php-fpm vendor/bin/php-cs-fixer fix

code-check: ## Run every check
	@make test
	@make phpstan
	@make deptrac
	@$(DOCKER_COMPOSE) exec php-fpm vendor/bin/php-cs-fixer check

%:
	@:
