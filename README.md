# SaaS Sandbox

Песочница для прототипов SaaS-бэкенда: PHP 7.4 + Yii2 в Docker, рядом PostgreSQL, Redis и RabbitMQ. Поднимается одной командой, со строгим тулчейном качества и заготовкой под фоновые задачи.

## Что внутри

- Nginx + PHP 7.4-FPM (Yii2 Basic)
- PostgreSQL 16 — хранилище
- Redis 7 — кэш Yii, по сокетам (без `ext-redis`)
- RabbitMQ 3 — брокер для `yii2-queue` (драйвер `amqp_interop`)

## Запуск

```bash
make up        # поднять стек
make install   # composer install внутри контейнера
```

Сайт открывается на http://localhost:8000. Состояние сервисов — на `/health` (HTML, либо `?format=json` с кодом 200/503). Админка RabbitMQ — http://localhost:15672, логин/пароль `guest`/`guest`.

Контейнер PHP работает от UID/GID хоста, поэтому файлы не достаются root. Если id нестандартный — `cp .env.example .env` и поправь (либо просто пользуйся `make`, он подставит id сам).

## Команды

Основное (`make help` — весь список):

```bash
make dev       # автоправки: rector + php-cs-fixer
make analyze   # phpstan
make test      # unit-тесты
make ci        # полный гейт, как в GitHub Actions
```

## Очереди

```bash
make shell
./yii queue-demo/push "привет"   # поставить задачу
./yii queue/listen               # запустить воркер
```

Из кода: `Yii::$app->queue->push(new \app\jobs\DemoJob(['message' => '...']))`.

## Качество

`make ci` (и CI на каждый push) прогоняет всё разом: `strict_types` везде, PHPStan level 6 со strict-rules, PHP-CS-Fixer (PSR-12), Rector под 7.4 и аудит зависимостей. Baseline нет — код проходит начисто.

## Дальше

- `docs/ai/contract.md` — стандарты кода и спецификация.
- `CLAUDE.md` — инструкции для ИИ-ассистента.
