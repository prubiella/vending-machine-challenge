DOCKER_COMPOSE := docker compose
ARGS = $(filter-out $@,$(MAKECMDGOALS))
CERTIFICATES := $(addprefix etc/infrastructure/certs/,local-cert.pem local-key.pem rootCA.pem)
TARGETS_WITH_ARGS := install update require require-dev logs run run-debug test coverage

.DEFAULT_GOAL := help

##@ Help

.PHONY: help

help: ## Show this help screen
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make \033[36m<target>\033[0m\n"} /^[a-zA-Z0-9_-]+:.*?##/ { printf "  \033[36m%-25s\033[0m %s\n", $$1, $$2 } /^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) } ' $(MAKEFILE_LIST)

##@ Build
.PHONY: setup-host start stop down destroy first-run

setup-host: ## Add the project hostname to /etc/hosts and create its local TLS certificate
	@./etc/infrastructure/setup-hostname.sh
	@./etc/infrastructure/setup-ssl.sh

start: ## Build and start the containers
	@for file in $(CERTIFICATES); do \
		[ -f "$$file" ] || { echo "Missing $$file: run 'make setup-host' first"; exit 1; }; \
	done
	@$(DOCKER_COMPOSE) up -d --build --remove-orphans

stop: ## Stop the containers
	@$(DOCKER_COMPOSE) stop

down: ## Stop and remove the containers
	@$(DOCKER_COMPOSE) down

destroy: ## Remove the containers and their volumes (the database is lost)
	@$(DOCKER_COMPOSE) down -v

first-run: ## Create .env, set up the host and start the containers
	@cp -n .env.dist .env || true
	@sed -i.bak -e "s/^USER_ID=.*/USER_ID=$$(id -u)/" -e "s/^GROUP_ID=.*/GROUP_ID=$$(id -g)/" .env && rm .env.bak
	@$(MAKE) setup-host
	@$(MAKE) start
	@$(MAKE) install

##@ Composer
.PHONY: install update require require-dev

install: ## Install the dependencies from composer.lock
	@$(DOCKER_COMPOSE) exec php-fpm composer install $(ARGS)

update: ## Update the dependencies within the composer.json constraints
	@$(DOCKER_COMPOSE) exec php-fpm composer update $(ARGS)

require: ## Add a dependency
	@$(DOCKER_COMPOSE) exec php-fpm composer require $(ARGS)

require-dev: ## Add a development dependency
	@$(DOCKER_COMPOSE) exec php-fpm composer require --dev $(ARGS)

##@ Development
.PHONY: run run-debug logs status shell db-shell

run: ## Run a console command: make run -- <command> [options], option values after a space
	@$(DOCKER_COMPOSE) exec php-fpm bin/console $(ARGS)

run-debug: ## Same as run, with Xdebug connecting to the IDE: make run-debug -- <command> [options]
	@$(DOCKER_COMPOSE) exec -e PHP_XDEBUG_MODE=debug php-fpm bin/console $(ARGS)

logs: ## Show the containers logs
	@$(DOCKER_COMPOSE) logs $(ARGS)

status: ## Show the containers status
	@$(DOCKER_COMPOSE) ps

shell: ## Open a shell in the PHP container
	@$(DOCKER_COMPOSE) exec php-fpm /bin/bash

db-shell: ## Open a MySQL shell on the dev database
	@$(DOCKER_COMPOSE) exec mysql sh -c 'MYSQL_PWD="$$MYSQL_PASSWORD" mysql -u"$$MYSQL_USER" "$$MYSQL_DATABASE"'

##@ Quality
.PHONY: test coverage phpstan deptrac cs-fixer code-check

test: ## Run the unit and integration tests
	@$(DOCKER_COMPOSE) exec php-fpm vendor/bin/phpunit --testdox $(ARGS)

coverage: ## Run the tests with an HTML coverage report in var/coverage
	@$(DOCKER_COMPOSE) exec -e PHP_XDEBUG_MODE=coverage php-fpm vendor/bin/phpunit --coverage-html var/coverage $(ARGS)

phpstan: ## Run the static analysis at the max level
	@$(DOCKER_COMPOSE) exec php-fpm vendor/bin/phpstan analyse

deptrac: ## Check the dependencies between layers
	@$(DOCKER_COMPOSE) exec php-fpm vendor/bin/deptrac

cs-fixer: ## Fix the coding standard
	@$(DOCKER_COMPOSE) exec php-fpm vendor/bin/php-cs-fixer fix

code-check: ## Run every check, the coding standard in check mode
	@$(MAKE) test
	@$(MAKE) phpstan
	@$(MAKE) deptrac
	@$(DOCKER_COMPOSE) exec php-fpm vendor/bin/php-cs-fixer check

ifneq ($(filter $(firstword $(MAKECMDGOALS)),$(TARGETS_WITH_ARGS)),)
%:
	@:
endif
