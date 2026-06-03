<?php

declare(strict_types=1);

namespace app\services;

use app\components\HealthChecker;

/**
 * Сервис проверки состояния инфраструктуры.
 *
 * Оркестрирует компонент проверки: один раз прогоняет пробы и на их основе
 * собирает отчёт ({@see HealthReport}). Сам компонент {@see HealthChecker}
 * скрыт за этим сервисом, поэтому контроллеры и команды зависят только от
 * единой операции получения отчёта.
 */
class HealthService
{
    /**
     * @var HealthChecker Компонент прогона проб и агрегации результатов
     */
    private $healthChecker;

    /**
     * @param HealthChecker $healthChecker Компонент проверки (внедряется контейнером)
     */
    public function __construct(HealthChecker $healthChecker)
    {
        $this->healthChecker = $healthChecker;
    }

    /**
     * Прогоняет проверки инфраструктуры и собирает готовый отчёт о состоянии.
     *
     * @return HealthReport Срез состояния: результаты проверок, признак здоровья и статус
     */
    public function report(): HealthReport
    {
        $checks = $this->healthChecker->run();

        return new HealthReport(
            $checks,
            $this->healthChecker->isHealthy($checks),
            $this->healthChecker->status($checks)
        );
    }
}
