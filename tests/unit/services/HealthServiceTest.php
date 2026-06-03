<?php

declare(strict_types=1);

namespace tests\unit\services;

use app\components\HealthChecker;
use app\services\HealthService;

/**
 * Тесты сервиса HealthService.
 *
 * Компонент проверки подменяется моком, поэтому тесты детерминированы и не
 * обращаются к реальной инфраструктуре — проверяется, что сервис прогоняет
 * пробы один раз и упаковывает их результат в цельный отчёт.
 */
class HealthServiceTest extends \Codeception\Test\Unit
{
    /**
     * report() собирает отчёт из результатов компонента: проверки, признак
     * здоровья и текстовый статус.
     */
    public function testReportAggregatesCheckerResult(): void
    {
        $checks = [
            ['name' => 'PostgreSQL', 'ok' => true, 'detail' => 'v16', 'latency_ms' => 1],
            ['name' => 'Redis', 'ok' => true, 'detail' => 'pong', 'latency_ms' => 2],
        ];

        $checker = $this->createMock(HealthChecker::class);
        $checker->expects($this->once())->method('run')->willReturn($checks);
        $checker->method('isHealthy')->with($checks)->willReturn(true);
        $checker->method('status')->with($checks)->willReturn('ok');

        $report = (new HealthService($checker))->report();

        verify($report->getChecks())->equals($checks);
        verify($report->isHealthy())->true();
        verify($report->getStatus())->equals('ok');
    }

    /**
     * Если компонент сообщает о сбое, отчёт отражает нездоровое состояние.
     */
    public function testReportReflectsUnhealthyState(): void
    {
        $checks = [
            ['name' => 'RabbitMQ', 'ok' => false, 'detail' => 'broker unreachable', 'latency_ms' => 9],
        ];

        $checker = $this->createMock(HealthChecker::class);
        $checker->method('run')->willReturn($checks);
        $checker->method('isHealthy')->with($checks)->willReturn(false);
        $checker->method('status')->with($checks)->willReturn('degraded');

        $report = (new HealthService($checker))->report();

        verify($report->isHealthy())->false();
        verify($report->getStatus())->equals('degraded');
    }
}
