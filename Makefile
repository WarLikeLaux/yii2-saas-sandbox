DC ?= docker compose
PHP := $(DC) exec -T php

export UID := $(shell id -u)
export GID := $(shell id -g)

.DEFAULT_GOAL := help
.PHONY: help up down build install shell health migrate env logs dev cs cs-check rector rector-check analyze test audit docs ci repomix

DOCTUM_PHAR ?= tools/doctum.phar
DOCTUM_VERSION ?= 5.5

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

migrate: ## применить миграции напрямую к postgres (минуя PgBouncer)
	$(DC) exec -T -e DB_HOST=postgres -e DB_PORT=5432 php ./yii migrate --interactive=0

env: ## пересобрать .env из .env.example (интерактивно, с сохранением текущих значений)
	@bash bin/gen-env.sh

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

audit: ## аудит зависимостей на уязвимости
	$(PHP) composer audit --abandoned=report

docs-install: ## скачать Doctum PHAR (ветка 5.5 — последняя с поддержкой PHP 7.4)
	mkdir -p tools
	curl -L https://doctum.long-term.support/releases/$(DOCTUM_VERSION)/doctum.phar -o $(DOCTUM_PHAR)

docs: ## сгенерировать API-документацию через Doctum (в контейнере, PHP 7.4)
	@test -f $(DOCTUM_PHAR) || $(MAKE) docs-install
	$(PHP) php $(DOCTUM_PHAR) update docs/doctum.php --ignore-parse-errors

ci: cs-check analyze rector-check test audit ## полный гейт (как в CI): стиль + анализ + рефакторинг + тесты + аудит

repomix: ## собрать исходники проекта в один файл (repomix-output.md, настройки в repomix.config.json)
	npx --yes repomix
	@echo "Готово: repomix-output.md"
