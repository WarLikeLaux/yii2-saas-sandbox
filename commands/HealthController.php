<?php

declare(strict_types=1);

namespace app\commands;

use app\services\HealthService;
use yii\base\Module;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Проверка связности инфраструктуры песочницы: PostgreSQL, Redis, RabbitMQ.
 *
 * ```
 * Запуск: ./yii health
 * Web-аналог: GET /health (HTML) и GET /health?format=json
 * ```
 */
class HealthController extends Controller
{
    /**
     * @var HealthService Сервис проверки состояния инфраструктуры
     */
    private $healthService;

    /**
     * @param string $id Идентификатор контроллера
     * @param Module $module Модуль, которому принадлежит контроллер
     * @param HealthService $healthService Сервис проверки (внедряется контейнером)
     * @param array<string, mixed> $config Дополнительная конфигурация
     */
    public function __construct(string $id, Module $module, HealthService $healthService, array $config = [])
    {
        $this->healthService = $healthService;
        parent::__construct($id, $module, $config);
    }

    /**
     * Запускает проверки и печатает результат в консоль.
     *
     * @return int Код возврата: 0 — все сервисы доступны, иначе 1
     */
    public function actionIndex(): int
    {
        $report = $this->healthService->report();

        foreach ($report->getChecks() as $check) {
            if ($check['ok']) {
                $this->stdout("[OK]   {$check['name']}: {$check['detail']} ({$check['latency_ms']}ms)\n");
            } else {
                $this->stderr("[FAIL] {$check['name']}: {$check['detail']}\n");
            }
        }

        return $report->isHealthy() ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }
}
