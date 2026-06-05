<?php

declare(strict_types=1);

namespace app\services;

use yii\redis\Connection;

/**
 * Ограничитель частоты запросов по алгоритму «token bucket» поверх Redis.
 *
 * Применяется, чтобы соблюдать лимиты внешних API (запросы к площадкам) и
 * защищать собственные точки входа от всплесков. Каждому ключу соответствует
 * «ведро» с токенами: запрос разрешается, если в ведре есть токен, и тратит
 * его; токены пополняются с фиксированной скоростью во времени. «Ведро»
 * сглаживает короткие всплески (до `capacity` запросов подряд), но держит
 * среднюю частоту на уровне `rate` в секунду.
 *
 * Вся проверка-и-списание выполняется одним Lua-скриптом — атомарно на стороне
 * Redis, поэтому параллельные процессы не могут «пробить» лимит за счёт гонки.
 */
class RateLimiterService
{
    /**
     * Lua-скрипт token bucket: пополняет ведро по времени и пытается списать
     * один токен. Возвращает 1, если запрос разрешён, иначе 0.
     */
    private const SCRIPT = <<<'LUA'
local capacity = tonumber(ARGV[1])
local rate = tonumber(ARGV[2])
local now = tonumber(ARGV[3])
local data = redis.call('HMGET', KEYS[1], 'tokens', 'ts')
local tokens = tonumber(data[1])
local ts = tonumber(data[2])
if tokens == nil then tokens = capacity; ts = now end
tokens = math.min(capacity, tokens + math.max(0, now - ts) * rate)
local allowed = 0
if tokens >= 1 then tokens = tokens - 1; allowed = 1 end
redis.call('HMSET', KEYS[1], 'tokens', tokens, 'ts', now)
redis.call('EXPIRE', KEYS[1], math.ceil(capacity / rate) + 1)
return allowed
LUA;

    /**
     * @var Connection Соединение с Redis (внедряется контейнером)
     */
    private $redis;

    /**
     * @param Connection $redis Соединение с Redis
     */
    public function __construct(Connection $redis)
    {
        $this->redis = $redis;
    }

    /**
     * Проверяет, разрешён ли очередной запрос по ключу, и при разрешении
     * списывает токен.
     *
     * @param string $key Ключ лимита (например, имя внешнего API или клиента)
     * @param int $capacity Ёмкость ведра — сколько запросов подряд допустимо
     * @param float $rate Скорость пополнения токенов в секунду (средняя частота)
     * @return bool true, если запрос разрешён; false, если лимит исчерпан
     */
    public function allow(string $key, int $capacity, float $rate): bool
    {
        $result = $this->redis->executeCommand('EVAL', [
            self::SCRIPT,
            1,
            'rate:' . $key,
            $capacity,
            $rate,
            time(),
        ]);

        return (int) $result === 1;
    }
}
