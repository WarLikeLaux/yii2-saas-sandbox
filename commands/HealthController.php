<?php

declare(strict_types=1);

namespace app\commands;

use app\components\HealthChecker;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Проверка связности инфраструктуры песочницы: PostgreSQL, Redis, RabbitMQ.
 *
 * Запуск: ./yii health
 * Web-аналог: GET /health (HTML) и GET /health?format=json
 */
class HealthController extends Controller
{
    /**
     * Запускает проверки и печатает результат в консоль.
     *
     * @return int Код возврата: 0 — все сервисы доступны, иначе 1
     */
    public function actionIndex(): int
    {
        $checker = new HealthChecker();
        $checks = $checker->run();

        foreach ($checks as $check) {
            if ($check['ok']) {
                $this->stdout("[OK]   {$check['name']}: {$check['detail']} ({$check['latency_ms']}ms)\n");
            } else {
                $this->stderr("[FAIL] {$check['name']}: {$check['detail']}\n");
            }
        }

        return $checker->isHealthy($checks) ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }
}
