DOCKER_COMPOSE := docker compose
ARGS = $(filter-out $@,$(MAKECMDGOALS))
CERTIFICATES := $(addprefix etc/infrastructure/certs/,local-cert.pem local-key.pem rootCA.pem)
TARGETS_WITH_ARGS := install update require require-dev logs run run-debug test behat coverage

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

first-run: ## Create .env, set up the host, start the containers, install the dependencies, create the token keys, migrate the databases and enable the git hooks
	@cp -n .env.dist .env || true
	@sed -i.bak -e "s/^USER_ID=.*/USER_ID=$$(id -u)/" -e "s/^GROUP_ID=.*/GROUP_ID=$$(id -g)/" .env && rm .env.bak
	@$(MAKE) setup-host
	@$(MAKE) start
	@$(MAKE) install
	@$(MAKE) jwt-keys
	@$(MAKE) migrate
	@$(MAKE) hooks

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
.PHONY: run run-debug logs status shell db-shell mongo-shell tools tools-stop migrate jwt-keys hooks

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

mongo-shell: ## Open a MongoDB shell on the dev database
	@$(DOCKER_COMPOSE) exec mongo sh -c 'mongosh -u "$$MONGO_INITDB_ROOT_USERNAME" -p "$$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase admin "$$MONGO_INITDB_DATABASE"'

tools: ## Start phpMyAdmin and mongo-express (local development only)
	@$(DOCKER_COMPOSE) --profile tools up -d phpmyadmin mongo-express

tools-stop: ## Stop and remove phpMyAdmin and mongo-express
	@$(DOCKER_COMPOSE) --profile tools rm --stop --force phpmyadmin mongo-express

migrate: ## Run the database migrations on the dev and test databases
	@$(DOCKER_COMPOSE) exec php-fpm bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
	@$(DOCKER_COMPOSE) exec php-fpm bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=test

jwt-keys: ## Create the key pair that signs the technicians' tokens, in config/jwt (git ignores it), unless it exists
	@$(DOCKER_COMPOSE) exec php-fpm bin/console lexik:jwt:generate-keypair --skip-if-exists

hooks: ## Use the project's git hooks (they run inside the PHP image)
	@git config core.hooksPath etc/git/hooks-docker

##@ Quality
.PHONY: test behat coverage phpstan deptrac cs-fixer cs-check lint-container code-check

test: ## Run the unit and integration tests
	@$(DOCKER_COMPOSE) exec php-fpm vendor/bin/phpunit --testdox $(ARGS)

behat: ## Run the customer and technician scenarios of features/ against the kernel (no server, no hosts entry)
	@$(DOCKER_COMPOSE) exec php-fpm vendor/bin/behat $(ARGS)

coverage: ## Run the tests with an HTML coverage report in var/coverage
	@$(DOCKER_COMPOSE) exec -e PHP_XDEBUG_MODE=coverage php-fpm vendor/bin/phpunit --coverage-html var/coverage $(ARGS)

phpstan: ## Run the static analysis at the max level
	@$(DOCKER_COMPOSE) exec php-fpm vendor/bin/phpstan analyse

deptrac: ## Check the dependencies between layers, and that every class belongs to one
	@$(DOCKER_COMPOSE) exec php-fpm vendor/bin/deptrac --fail-on-uncovered
	@$(DOCKER_COMPOSE) exec php-fpm vendor/bin/deptrac debug:unassigned

cs-fixer: ## Fix the coding standard
	@$(DOCKER_COMPOSE) exec php-fpm vendor/bin/php-cs-fixer fix

cs-check: ## Check the coding standard without changing files
	@$(DOCKER_COMPOSE) exec php-fpm vendor/bin/php-cs-fixer check

lint-container: ## Check that the service container compiles and every service is wired
	@$(DOCKER_COMPOSE) exec php-fpm bin/console lint:container

code-check: ## Run every check, the coding standard in check mode
	@$(MAKE) test
	@$(MAKE) behat
	@$(MAKE) phpstan
	@$(MAKE) deptrac
	@$(MAKE) cs-check
	@$(MAKE) lint-container

ifneq ($(filter $(firstword $(MAKECMDGOALS)),$(TARGETS_WITH_ARGS)),)
%:
	@:
endif
