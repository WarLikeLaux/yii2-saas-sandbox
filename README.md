# SaaS Sandbox

Песочница для прототипирования бэкенда B2B SaaS-сервиса: **PHP 7.4 / Yii2 Basic** в Docker, с PostgreSQL, Redis и RabbitMQ — со строгим тулчейном качества и фоновыми задачами.

## Стек

- **Nginx** + **PHP 7.4-FPM** (Yii2 Basic)
- **PostgreSQL 16**, **Redis 7**, **RabbitMQ 3** (management)
- Очереди — `yiisoft/yii2-queue`, драйвер `amqp_interop` поверх RabbitMQ
- Кэш Yii — Redis (`yii\redis\Cache`, по сокетам)

## Требования

- Docker и Docker Compose v2
- Linux-хост: контейнер `php` запускается от UID/GID хоста, поэтому созданные в нём файлы не принадлежат root. На другом UID/GID — `cp .env.example .env` и поправить, либо использовать `make` (он подставляет id хоста сам).

## Быстрый старт

```bash
make up        # поднять весь стек
make install   # composer install внутри контейнера
make health    # проверить связность PostgreSQL / Redis / RabbitMQ
```

Открыть [http://localhost:8000](http://localhost:8000).

## Доступы

| Сервис | Адрес | Доступ |
|---|---|---|
| Сайт | http://localhost:8000 | — |
| Health | http://localhost:8000/health | HTML; `?format=json` — JSON, код 200/503 |
| RabbitMQ UI | http://localhost:15672 | `guest` / `guest` |
| PostgreSQL | `localhost:5432` | БД `yii2basic`, `yii2` / `secret` |
| Redis | `localhost:6379` | — |

> Учётные данные — дефолтные для песочницы; для прода выносятся в окружение.

## Команды

| Команда | Назначение |
|---|---|
| `make up` / `make down` | поднять / остановить стек |
| `make build` | пересобрать образ php |
| `make install` | composer install в контейнере |
| `make shell` | войти в контейнер php |
| `make logs` | хвост логов сервисов |
| `make health` | проверка связности (`./yii health`) |
| `make dev` | автоправки: rector + php-cs-fixer |
| `make cs` / `make cs-check` | стиль: исправить / проверить |
| `make rector` / `make rector-check` | рефакторинг: применить / показать |
| `make analyze` | статический анализ (phpstan) |
| `make test` | строго unit-тесты |
| `make audit` | аудит зависимостей на уязвимости |
| `make ci` | полный гейт (как в CI) |

## Очереди

```bash
make shell
./yii queue-demo/push "сообщение"   # поставить демо-задачу
./yii queue/listen                  # воркер (демон)
```

В коде: `Yii::$app->queue->push(new \app\jobs\DemoJob(['message' => '...']))`. У драйвера `amqp_interop` есть только `queue/listen` и служебный `queue/exec` (команд `run`/`info` нет).

## Качество кода

Строгий тулчейн (запускается `make ci`, а также в GitHub Actions на каждый push):

- **`declare(strict_types=1)`** обязателен во всех файлах
- **PHPStan** level 6 + `phpstan-strict-rules` (запрет `==`/`!=`, не-bool условий, `empty()`, коротких тернарников и т.п.)
- **PHP-CS-Fixer** — PSR-12 + правила оформления
- **Rector** — рефакторинг с таргетом PHP 7.4
- **composer audit** + `roave/security-advisories` — безопасность зависимостей

Замечания в стоковом коде шаблона Yii2 вынесены в `phpstan-baseline.neon`; новый код держится строго.

## Тесты

```bash
make test
```

Запускает строго unit-suite Codeception (без обращения к БД).

## Документация для разработки

- `docs/ai/contract.md` — спецификация и стандарты кода (источник истины).
- `CLAUDE.md` — краткий обзор инфраструктуры и неочевидных решений.
