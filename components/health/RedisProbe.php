<?php

declare(strict_types=1);

namespace app\components\health;

use yii\redis\Connection;

/**
 * Проба доступности Redis.
 *
 * Пишет и читает контрольный ключ, дополнительно возвращает версию сервера.
 */
class RedisProbe implements ProbeInterface
{
    /**
     * @var Connection Соединение с Redis
     */
    private $redis;

    /**
     * @param Connection $redis Соединение с Redis (внедряется контейнером)
     */
    public function __construct(Connection $redis)
    {
        $this->redis = $redis;
    }

    /**
     * Возвращает имя сервиса.
     *
     * @return string Имя сервиса
     */
    public function name(): string
    {
        return 'Redis';
    }

    /**
     * Пишет и читает контрольный ключ, затем возвращает версию сервера.
     *
     * @return string Версия Redis и результат set/get
     * @throws \Throwable Если соединение с Redis не удалось
     */
    public function check(): string
    {
        $this->redis->executeCommand('SET', ['health:check', 'pong']);

        $rawValue = $this->redis->executeCommand('GET', ['health:check']);
        $value = is_string($rawValue) ? $rawValue : '';

        $rawInfo = $this->redis->executeCommand('INFO', ['server']);
        $info = is_string($rawInfo) ? $rawInfo : '';
        preg_match('/redis_version:([^\r\n]+)/', $info, $matches);

        return 'v' . ($matches[1] ?? 'unknown') . ', set/get -> ' . $value;
    }
}
