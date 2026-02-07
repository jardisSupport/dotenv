SHELL := bash
.SHELLFLAGS := -eu -o pipefail -c
MAKEFLAGS += --warn-undefined-variables
DOCKER_COMPOSE := docker compose

include .env

help:
	@echo -e "\033[0;32m Usage: make [target] "
	@echo
	@echo -e "\033[1m targets:\033[0m"
	@egrep '^(.+):*\ ##\ (.+)' ${MAKEFILE_LIST} | column -t -c 2 -s ':#'
.PHONY: help

<---composer----->: ## -----------------------------------------------------------------------
install: ## Run composer install
	$(DOCKER_COMPOSE) run --rm phpcli composer install --no-cache
.PHONY: install

update: ## run composer update
	$(DOCKER_COMPOSE) run --rm -it -e XDEBUG_MODE=off phpcli composer update

autoload: ## Run composer dump-autoload
	$(DOCKER_COMPOSE) run --rm phpcli composer dumpautoload
.PHONY: autoload

<---qa tools----->: ## -----------------------------------------------------------------------
phpunit: ## Run all tests -> phpunit.xml
	$(DOCKER_COMPOSE) run --rm phpcli vendor/bin/phpunit --bootstrap ./tests/bootstrap.php /app/tests
.PHONY: phpunit

phpunit-reports: ## Run all tests with reports -> clover and coverage
	$(DOCKER_COMPOSE) run --rm -e PCOV_ENABLED=1 phpcli vendor/bin/phpunit --bootstrap ./tests/bootstrap.php /app/tests --coverage-clover tests/reports/clover.xml --coverage-xml tests/reports/coverage-xml
.PHONY: phpunit-reports

phpunit-coverage: ## Run all tests to find test leaks
	$(DOCKER_COMPOSE) run --rm -e PCOV_ENABLED=1 phpcli vendor/bin/phpunit --bootstrap ./tests/bootstrap.php /app/tests --coverage-text
.PHONY: phpunit-coverage

phpunit-coverage-html: ## Run all tests to find test leaks html
	$(DOCKER_COMPOSE) run --rm -e PCOV_ENABLED=1 phpcli vendor/bin/phpunit --bootstrap ./tests/bootstrap.php /app/tests --coverage-html tests/reports/coverage-html
.PHONY: phpunit-coverage-html

phpstan: ## Run analyse source in src -> phpstan.neon
	$(DOCKER_COMPOSE) run --rm phpcli vendor/bin/phpstan analyse /app/src -c phpstan.neon
.PHONY: phpstan

phpcs: ## Run coding standards -> phpcs.cml
	$(DOCKER_COMPOSE) run --rm phpcli vendor/bin/phpcs /app/src
.PHONY: phpcs

<---docker------->: ## -----------------------------------------------------------------------
shell: ## Run a shell inside the container
	$(DOCKER_COMPOSE) run --rm -it phpcli sh
.PHONY: shell

remove: ## Stops and removes containers, images, network, volumes and caches
	$(DOCKER_COMPOSE) down --volumes --remove-orphans --rmi "all"
	@docker images --filter dangling=true -q | xargs -r docker rmi

<---ssh -------->: ## -----------------------------------------------------------------------
ssh-agent: ## Get SSH agent ready
	eval `ssh-agent -s`
	ssh-add
.PHONY: ssh-agent
