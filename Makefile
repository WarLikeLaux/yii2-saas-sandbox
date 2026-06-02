DC ?= docker compose
PHP := $(DC) exec -T php

export UID := $(shell id -u)
export GID := $(shell id -g)

.DEFAULT_GOAL := help
.PHONY: help up down build install shell health logs dev cs cs-check rector rector-check analyze test

help: ## список команд
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

up: ## поднять весь стек
	$(DC) up -d

down: ## остановить стек
	$(DC) down

build: ## пересобрать образ php
	$(DC) build php

install: ## composer install внутри контейнера
	$(PHP) composer install

shell: ## войти в контейнер php
	$(DC) exec php sh

logs: ## хвост логов всех сервисов
	$(DC) logs -f --tail=100

health: ## проверить связность сервисов (./yii health)
	$(PHP) ./yii health

cs: ## исправить стиль кода (php-cs-fixer)
	$(PHP) vendor/bin/php-cs-fixer fix

cs-check: ## проверить стиль без правок
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff

rector: ## применить рефакторинги (rector)
	$(PHP) vendor/bin/rector process

rector-check: ## показать рефакторинги без правок
	$(PHP) vendor/bin/rector process --dry-run

dev: rector cs ## dev-цикл: rector + php-cs-fixer (автоправки)

analyze: ## статический анализ (phpstan)
	$(PHP) vendor/bin/phpstan analyse

test: ## строго юнит-тесты (codeception unit)
	$(PHP) vendor/bin/codecept run unit
