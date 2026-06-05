# SaaS Sandbox

Песочница high-load бэкенда на **PHP 7.4 + Yii2** в Docker: PostgreSQL за PgBouncer, Redis, RabbitMQ. Не «hello world», а рабочий стенд с настоящими приёмами высоких нагрузок и отказоустойчивости — keyset-пагинация, денормализация, пакетная заливка миллионов строк, очереди, идемпотентность, transactional outbox, circuit breaker, rate limiting, мьютексы, кеш с защитой от stampede, multi-tenancy на RLS и наблюдаемость (структурные логи, Sentry, ELK). Всё под строгим тулчейном качества (PHPStan level 9, strict-rules).

## Стек

| Слой | Технология |
|---|---|
| Web | Nginx + PHP 7.4-FPM (Yii2 Basic) |
| БД | PostgreSQL 16 **за PgBouncer** в transaction mode |
| Кеш / блокировки | Redis 7 (`yii2-redis`) |
| Очереди | RabbitMQ 3 (`yii2-queue`, драйвер `amqp_interop`) |
| Наблюдаемость | Sentry SDK + ELK (Elasticsearch + Kibana + filebeat) — опционально |

## Запуск

```bash
make up                                            # поднять стек
make install                                       # composer install в контейнере
make shell                                         # войти в php-контейнер
./yii migrate --interactive=0                      # применить миграции
./yii seed/messages 1000000 1000 --truncate        # залить ~1 млн сообщений
```

- Приложение — http://localhost:8000, лента сообщений — `/messages`
- Здоровье сервисов — `/health` (HTML или `?format=json`, код 200/503)
- Adminer (БД) — http://localhost:8080, RabbitMQ — http://localhost:15672 (`guest`/`guest`)

Контейнер PHP работает от UID/GID хоста — файлы не достаются root. `make migrate` ходит в Postgres напрямую (минуя PgBouncer), как и положено для DDL.

## Что внутри: приёмы и где потрогать

Каждый приём реализован в коде и снабжён демо-командой (`make shell`, затем `./yii ...`).

### Базы данных (PostgreSQL)

| Приём | Где | Потрогать |
|---|---|---|
| Keyset-пагинация ленты | `services/MessageFeed.php` | `/messages` |
| «Последнее сообщение чата» через loose scan + `LATERAL` | `services/MessageFeed.php` | `/messages` |
| Поиск по тексту (`pg_trgm` + адаптивная стратегия) | `services/MessageFeed.php` | поиск на `/messages` |
| Денормализация списка чатов + триггеры (`INSERT`/`UPDATE`/`DELETE`) | `migrations/*_chats*` | — |
| Пакетная заливка (`batchInsert` / `COPY`) | `services/MessageSeeder.php` | `./yii seed/messages` |
| Блокировки от дедлоков (`FOR UPDATE` по сорт. id) | `services/MessageStatusService.php` | `./yii message-status/set 1 10 20` |
| Серверные таймауты роли | `migrations/*_set_role_timeouts` | — |
| Multi-tenancy через Row-Level Security | `migrations/*_add_tenant_rls` | `./yii rls-demo/show` |

### Очереди и отказоустойчивость

| Приём | Где | Потрогать |
|---|---|---|
| Фоновые задачи (RabbitMQ) | `services/QueueService.php`, `jobs/` | `./yii queue-demo/push "привет"` + `./yii queue/listen` |
| Transactional outbox + relay (`FOR UPDATE SKIP LOCKED`) | `services/OutboxService.php` | `./yii outbox/demo 3` + `./yii outbox/relay` |
| Single-flight через mutex (Redis) | `services/MutexService.php` | `./yii mutex-demo/demo 42` |
| Circuit breaker (Redis) | `services/CircuitBreakerService.php` | `./yii circuit-breaker-demo/run api` |
| Rate limiting (token bucket, Redis) | `services/RateLimiterService.php` | `./yii rate-demo/hit demo 10` |
| Приём вебхуков: HMAC-подпись + дедуп + быстрый ACK | `controllers/WebhookController.php` | `POST /webhook/receive` |

### Кеш и наблюдаемость

| Приём | Где | Потрогать |
|---|---|---|
| Кеш cache-aside + защита от stampede (single-flight) | `services/CacheService.php` | `./yii cache-demo/count` |
| Структурные JSON-логи + correlation id | `components/JsonLogTarget.php`, `components/CorrelationContext.php` | `./yii log-demo/write "..."` |
| Отлов ошибок в Sentry (no-op без DSN) | `services/SentryService.php` | `./yii sentry-demo/test` |
| Централизованные логи в Kibana (ELK) | профиль `observability` | см. ниже |

## Наблюдаемость (ELK)

ELK прожорлив, поэтому вынесен в отдельный профиль и по умолчанию не поднимается:

```bash
docker compose --profile observability up -d     # Elasticsearch + Kibana + filebeat
./yii log-demo/write "событие"                    # написать JSON-лог
```

filebeat доставляет `runtime/logs/app.json.log` в Elasticsearch (индекс `app-logs-*`). Kibana — http://localhost:5601 (создать data view `app-logs-*`, смотреть в Discover, фильтровать по `correlation_id` / `level` / `category`). Sentry включается заданием `SENTRY_DSN`.

## Команды и качество

```bash
make dev       # автоправки: rector + php-cs-fixer
make analyze   # phpstan (level 9 + strict-rules)
make test      # unit-тесты (codeception)
make ci        # полный гейт, как в CI: cs-check + analyze + rector-check + test + audit
```

`make ci` (и CI на каждый push) проходит начисто, без baseline: `strict_types` везде, PHPStan **level 9** со strict-rules, PHP-CS-Fixer (PSR-12), Rector под 7.4, аудит зависимостей. Весь PHPDoc — на русском.

## Дальше

- `docs/ai/contract.md` — стандарты кода и архитектурная спецификация (источник истины).
- `CLAUDE.md` — инструкции для ИИ-ассистента.
- `make help` — полный список команд.
