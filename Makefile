DOCKER_COMPOSE := docker compose
ARGS = $(filter-out $@,$(MAKECMDGOALS))
TARGETS_WITH_ARGS := install update require require-dev logs

.DEFAULT_GOAL := help

##@ Help

.PHONY: help

help: ## Show this help screen
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make \033[36m<target>\033[0m\n"} /^[a-zA-Z0-9_-]+:.*?##/ { printf "  \033[36m%-25s\033[0m %s\n", $$1, $$2 } /^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) } ' $(MAKEFILE_LIST)

##@ Build
.PHONY: start stop down destroy first-run

start: ## Build and start the containers
	@$(DOCKER_COMPOSE) up -d --build --remove-orphans

stop: ## Stop the containers
	@$(DOCKER_COMPOSE) stop

down: ## Stop and remove the containers
	@$(DOCKER_COMPOSE) down

destroy: ## Remove the containers and their volumes (the database is lost)
	@$(DOCKER_COMPOSE) down -v

first-run: ## Create .env and start the containers
	@cp -n .env.dist .env || true
	@sed -i.bak -e "s/^USER_ID=.*/USER_ID=$$(id -u)/" -e "s/^GROUP_ID=.*/GROUP_ID=$$(id -g)/" .env && rm .env.bak
	@$(MAKE) start

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
.PHONY: logs status shell db-shell

logs: ## Show the containers logs
	@$(DOCKER_COMPOSE) logs $(ARGS)

status: ## Show the containers status
	@$(DOCKER_COMPOSE) ps

shell: ## Open a shell in the PHP container
	@$(DOCKER_COMPOSE) exec php-fpm /bin/bash

db-shell: ## Open a MySQL shell on the dev database
	@$(DOCKER_COMPOSE) exec mysql sh -c 'MYSQL_PWD="$$MYSQL_PASSWORD" mysql -u"$$MYSQL_USER" "$$MYSQL_DATABASE"'

ifneq ($(filter $(firstword $(MAKECMDGOALS)),$(TARGETS_WITH_ARGS)),)
%:
	@:
endif
