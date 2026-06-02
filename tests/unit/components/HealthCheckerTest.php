<?php

declare(strict_types=1);

namespace tests\unit\components;

use app\components\health\ProbeInterface;
use app\components\HealthChecker;

/**
 * Тесты компонента HealthChecker.
 *
 * Благодаря инъекции проб через конструктор тесты полностью детерминированы
 * и не обращаются к реальным PostgreSQL/Redis/RabbitMQ — поведение сервисов
 * имитируется заглушками ProbeInterface.
 */
class HealthCheckerTest extends \Codeception\Test\Unit
{
    /**
     * Создаёт пробу-заглушку с заданным именем и поведением.
     *
     * @param string $name Имя сервиса
     * @param string|null $detail Деталь успешной проверки; null — проба бросает исключение
     * @return ProbeInterface Заглушка пробы
     */
    private function makeProbe(string $name, ?string $detail): ProbeInterface
    {
        return new class ($name, $detail) implements ProbeInterface {
            /**
             * @var string Имя сервиса
             */
            private $name;

            /**
             * @var string|null Деталь успешной проверки или null для имитации сбоя
             */
            private $detail;

            /**
             * @param string $name Имя сервиса
             * @param string|null $detail Деталь или null для имитации сбоя
             */
            public function __construct(string $name, ?string $detail)
            {
                $this->name = $name;
                $this->detail = $detail;
            }

            public function name(): string
            {
                return $this->name;
            }

            public function check(): string
            {
                if ($this->detail === null) {
                    throw new \RuntimeException('недоступно');
                }

                return $this->detail;
            }
        };
    }

    /**
     * Все проверки прошли успешно -> сервис считается здоровым.
     */
    public function testIsHealthyAllOk(): void
    {
        $checks = [
            ['name' => 'A', 'ok' => true, 'detail' => 'ok', 'latency_ms' => 1],
            ['name' => 'B', 'ok' => true, 'detail' => 'ok', 'latency_ms' => 2],
        ];

        verify((new HealthChecker([]))->isHealthy($checks))->true();
    }

    /**
     * Падение первой проверки -> сервис нездоров.
     */
    public function testIsHealthyFirstFailed(): void
    {
        $checks = [
            ['name' => 'A', 'ok' => false, 'detail' => 'connection refused', 'latency_ms' => 5],
            ['name' => 'B', 'ok' => true, 'detail' => 'ok', 'latency_ms' => 2],
        ];

        verify((new HealthChecker([]))->isHealthy($checks))->false();
    }

    /**
     * Падение последней проверки (а не первой) -> сервис всё равно нездоров.
     */
    public function testIsHealthyLastFailed(): void
    {
        $checks = [
            ['name' => 'A', 'ok' => true, 'detail' => 'ok', 'latency_ms' => 1],
            ['name' => 'B', 'ok' => false, 'detail' => 'broker unreachable', 'latency_ms' => 9],
        ];

        verify((new HealthChecker([]))->isHealthy($checks))->false();
    }

    /**
     * Пустой массив проверок считается здоровым (нет провалившихся).
     */
    public function testIsHealthyEmptyArray(): void
    {
        verify((new HealthChecker([]))->isHealthy([]))->true();
    }

    /**
     * run() прогоняет каждую пробу и собирает корректную структуру результата.
     */
    public function testRunMeasuresEachProbe(): void
    {
        $checker = new HealthChecker([
            $this->makeProbe('PostgreSQL', 'v16'),
            $this->makeProbe('Redis', null),
        ]);

        $checks = $checker->run();

        verify($checks)->arrayCount(2);

        verify($checks[0]['name'])->equals('PostgreSQL');
        verify($checks[0]['ok'])->true();
        verify($checks[0]['detail'])->equals('v16');

        verify($checks[1]['name'])->equals('Redis');
        verify($checks[1]['ok'])->false();
        verify($checks[1]['detail'])->equals('недоступно');
    }

    /**
     * Каждый элемент результата run() имеет нужные ключи и корректные типы.
     */
    public function testRunChecksHaveValidStructure(): void
    {
        $checker = new HealthChecker([
            $this->makeProbe('PostgreSQL', 'v16'),
            $this->makeProbe('Redis', 'pong'),
        ]);

        foreach ($checker->run() as $check) {
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

    /**
     * Если хотя бы одна проба упала, isHealthy() по результату run() даёт false.
     */
    public function testRunWithFailingProbeIsUnhealthy(): void
    {
        $checker = new HealthChecker([
            $this->makeProbe('PostgreSQL', 'v16'),
            $this->makeProbe('Redis', null),
        ]);

        verify($checker->isHealthy($checker->run()))->false();
    }
}
