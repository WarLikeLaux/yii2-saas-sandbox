<?php

declare(strict_types=1);

namespace tests\unit\components;

use app\components\HealthChecker;

class HealthCheckerTest extends \Codeception\Test\Unit
{
    /**
     * Все проверки прошли успешно -> сервис считается здоровым.
     */
    public function testIsHealthyAllOk()
    {
        $checks = [
            ['name' => 'PostgreSQL', 'ok' => true, 'detail' => 'ok', 'latency_ms' => 1],
            ['name' => 'Redis', 'ok' => true, 'detail' => 'ok', 'latency_ms' => 2],
            ['name' => 'RabbitMQ', 'ok' => true, 'detail' => 'ok', 'latency_ms' => 3],
        ];

        verify((new HealthChecker())->isHealthy($checks))->true();
    }

    /**
     * Падение первой проверки -> сервис нездоров.
     */
    public function testIsHealthyFirstFailed()
    {
        $checks = [
            ['name' => 'PostgreSQL', 'ok' => false, 'detail' => 'connection refused', 'latency_ms' => 5],
            ['name' => 'Redis', 'ok' => true, 'detail' => 'ok', 'latency_ms' => 2],
            ['name' => 'RabbitMQ', 'ok' => true, 'detail' => 'ok', 'latency_ms' => 3],
        ];

        verify((new HealthChecker())->isHealthy($checks))->false();
    }

    /**
     * Падение последней проверки (а не первой) -> сервис всё равно нездоров.
     */
    public function testIsHealthyLastFailed()
    {
        $checks = [
            ['name' => 'PostgreSQL', 'ok' => true, 'detail' => 'ok', 'latency_ms' => 1],
            ['name' => 'Redis', 'ok' => true, 'detail' => 'ok', 'latency_ms' => 2],
            ['name' => 'RabbitMQ', 'ok' => false, 'detail' => 'broker unreachable', 'latency_ms' => 9],
        ];

        verify((new HealthChecker())->isHealthy($checks))->false();
    }

    /**
     * Пустой массив проверок считается здоровым (нет провалившихся).
     */
    public function testIsHealthyEmptyArray()
    {
        verify((new HealthChecker())->isHealthy([]))->true();
    }

    /**
     * run() возвращает ровно три проверки с ожидаемыми именами сервисов.
     *
     * Тест толерантен к доступности инфраструктуры: исключения внутри проб
     * проглатываются, поэтому метод безопасно вызывать без поднятых сервисов.
     */
    public function testRunReturnsThreeNamedChecks()
    {
        $checks = (new HealthChecker())->run();

        verify($checks)->arrayCount(3);

        $names = [];
        foreach ($checks as $check) {
            $names[] = $check['name'];
        }

        verify($names)->equals(['PostgreSQL', 'Redis', 'RabbitMQ']);
    }

    /**
     * Каждая проверка из run() имеет нужные ключи и корректные типы значений.
     *
     * Конкретные значения ok не проверяются: они зависят от доступности
     * сервисов в тестовом окружении. Контролируется только структура.
     */
    public function testRunChecksHaveValidStructure()
    {
        $checks = (new HealthChecker())->run();

        foreach ($checks as $check) {
            verify($check)->arrayHasKey('name');
            verify($check)->arrayHasKey('ok');
            verify($check)->arrayHasKey('detail');
            verify($check)->arrayHasKey('latency_ms');

            verify($check['name'])->isString();
            verify($check['ok'])->isBool();
            verify($check['detail'])->isString();
            verify($check['latency_ms'])->isInt();
            verify($check['latency_ms'])->greaterThanOrEqual(0);
        }
    }
}
