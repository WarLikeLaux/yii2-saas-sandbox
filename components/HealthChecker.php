<?php

namespace app\components;

use Yii;

/**
 * Проверка связности инфраструктуры песочницы: PostgreSQL, Redis, RabbitMQ.
 *
 * Используется и консольной командой (./yii health), и web-страницей (/health),
 * чтобы логика проверок не дублировалась.
 */
class HealthChecker
{
    /**
     * @return array[] список проверок вида
     *               ['name' => string, 'ok' => bool, 'detail' => string, 'latency_ms' => int]
     */
    public function run(): array
    {
        return [
            $this->measure('PostgreSQL', [$this, 'checkPostgres']),
            $this->measure('Redis', [$this, 'checkRedis']),
            $this->measure('RabbitMQ', [$this, 'checkRabbitmq']),
        ];
    }

    /**
     * @return bool true, если все проверки прошли
     */
    public function isHealthy(array $checks): bool
    {
        foreach ($checks as $check) {
            if (!$check['ok']) {
                return false;
            }
        }

        return true;
    }

    private function measure(string $name, callable $probe): array
    {
        $start = microtime(true);

        try {
            $detail = $probe();
            $ok = true;
        } catch (\Throwable $e) {
            $detail = $e->getMessage();
            $ok = false;
        }

        return [
            'name' => $name,
            'ok' => $ok,
            'detail' => $detail,
            'latency_ms' => (int) round((microtime(true) - $start) * 1000),
        ];
    }

    private function checkPostgres(): string
    {
        return (string) Yii::$app->db->createCommand('SELECT version()')->queryScalar();
    }

    private function checkRedis(): string
    {
        Yii::$app->redis->set('health:check', 'pong');
        $value = Yii::$app->redis->get('health:check');

        $info = Yii::$app->redis->executeCommand('INFO', ['server']);
        preg_match('/redis_version:([^\r\n]+)/', $info, $m);

        return 'v' . ($m[1] ?? 'unknown') . ', set/get -> ' . $value;
    }

    private function checkRabbitmq(): string
    {
        $context = Yii::$app->queue->getContext();
        $context->createTemporaryQueue(); // форсирует реальное соединение с брокером
        $context->close();

        return 'yii2-queue/amqp_interop context established';
    }
}
