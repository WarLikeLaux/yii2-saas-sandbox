<?php

declare(strict_types=1);

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
     * Выполняет все проверки инфраструктуры.
     *
     * @return list<array{name: string, ok: bool, detail: string, latency_ms: int}> Результаты проверок
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
     * Проверяет, что все проверки прошли успешно.
     *
     * @param list<array{name: string, ok: bool, detail: string, latency_ms: int}> $checks Результаты проверок
     * @return bool true, если все сервисы доступны
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

    /**
     * Измеряет одну проверку: вызывает пробу и засекает время выполнения.
     *
     * @param string $name Человекочитаемое имя сервиса
     * @param callable(): string $probe Проба, возвращающая строку с деталями
     * @return array{name: string, ok: bool, detail: string, latency_ms: int} Результат проверки
     */
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

    /**
     * Проверяет соединение с PostgreSQL и возвращает версию сервера.
     *
     * @return string Строка версии PostgreSQL
     * @throws \Throwable Если соединение с БД не удалось
     */
    private function checkPostgres(): string
    {
        return (string) Yii::$app->db->createCommand('SELECT version()')->queryScalar();
    }

    /**
     * Проверяет Redis: пишет и читает ключ, возвращает версию сервера.
     *
     * @return string Версия Redis и результат set/get
     * @throws \Throwable Если соединение с Redis не удалось
     */
    private function checkRedis(): string
    {
        Yii::$app->redis->set('health:check', 'pong');
        $value = Yii::$app->redis->get('health:check');

        $info = Yii::$app->redis->executeCommand('INFO', ['server']);
        preg_match('/redis_version:([^\r\n]+)/', $info, $m);

        return 'v' . ($m[1] ?? 'unknown') . ', set/get -> ' . $value;
    }

    /**
     * Проверяет соединение с RabbitMQ через контекст yii2-queue.
     *
     * @return string Сообщение об успешном установлении соединения
     * @throws \Throwable Если соединение с брокером не удалось
     */
    private function checkRabbitmq(): string
    {
        $context = Yii::$app->queue->getContext();
        $context->createTemporaryQueue();
        $context->close();

        return 'yii2-queue/amqp_interop context established';
    }
}
