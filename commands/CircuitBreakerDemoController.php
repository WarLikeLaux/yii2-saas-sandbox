<?php

declare(strict_types=1);

namespace app\commands;

use app\services\CircuitBreakerService;
use yii\base\Module;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Демонстрация предохранителя (circuit breaker).
 *
 * Имитирует серию отказов внешнего API: после порога цепь размыкается и вызовы
 * быстро отклоняются (сервис не трогаем), а успешный вызов замыкает её обратно.
 *
 * ```
 * ./yii circuit-breaker-demo/run <ключ>
 * ```
 */
class CircuitBreakerDemoController extends Controller
{
    /**
     * @var CircuitBreakerService Предохранитель
     */
    private $breaker;

    /**
     * @param string $id Идентификатор контроллера
     * @param Module $module Модуль, которому принадлежит контроллер
     * @param CircuitBreakerService $breaker Предохранитель (внедряется контейнером)
     * @param array<string, mixed> $config Дополнительная конфигурация
     */
    public function __construct(
        string $id,
        Module $module,
        CircuitBreakerService $breaker,
        array $config = []
    ) {
        $this->breaker = $breaker;
        parent::__construct($id, $module, $config);
    }

    /**
     * Прогоняет серию отказов и показывает размыкание/замыкание цепи.
     *
     * @param string $key Имя защищаемого ресурса
     * @return int Код возврата (0 — успех)
     */
    public function actionRun(string $key = 'demo-api'): int
    {
        $this->breaker->recordSuccess($key);
        $this->stdout('Старт: доступен = ' . $this->yesNo($this->breaker->isAvailable($key)) . "\n");

        for ($i = 1; $i <= 5; $i++) {
            $this->breaker->recordFailure($key);
            $this->stdout("После отказа #{$i}: доступен = " . $this->yesNo($this->breaker->isAvailable($key)) . "\n");
        }

        $this->stdout("Цепь разомкнута — вызовы быстро отклоняются, лежащий сервис не долбим\n");

        $this->breaker->recordSuccess($key);
        $this->stdout('После восстановления (recordSuccess): доступен = ' . $this->yesNo($this->breaker->isAvailable($key)) . "\n");

        return ExitCode::OK;
    }

    /**
     * Превращает булево в «да»/«нет» для вывода.
     *
     * @param bool $value Значение
     * @return string «да» или «нет»
     */
    private function yesNo(bool $value): string
    {
        return $value ? 'да' : 'нет';
    }
}
