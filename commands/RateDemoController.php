<?php

declare(strict_types=1);

namespace app\commands;

use app\services\RateLimiterService;
use yii\base\Module;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Демонстрация ограничителя частоты (token bucket на Redis).
 *
 * Бьёт по лимиту серией запросов и показывает, сколько прошло, а сколько было
 * отклонено. При ёмкости ведра 5 первые 5 запросов подряд проходят, остальные
 * отклоняются, пока ведро не пополнится.
 *
 * ```
 * ./yii rate-demo/hit <ключ> <число-запросов>
 * ```
 */
class RateDemoController extends Controller
{
    /**
     * @var RateLimiterService Ограничитель частоты запросов
     */
    private $rateLimiter;

    /**
     * @param string $id Идентификатор контроллера
     * @param Module $module Модуль, которому принадлежит контроллер
     * @param RateLimiterService $rateLimiter Ограничитель частоты (внедряется контейнером)
     * @param array<string, mixed> $config Дополнительная конфигурация
     */
    public function __construct(
        string $id,
        Module $module,
        RateLimiterService $rateLimiter,
        array $config = []
    ) {
        $this->rateLimiter = $rateLimiter;
        parent::__construct($id, $module, $config);
    }

    /**
     * Делает серию запросов к лимиту и печатает, сколько разрешено/отклонено.
     *
     * @param string $key Ключ лимита
     * @param int $n Сколько запросов сделать подряд
     * @return int Код возврата (0 — успех)
     */
    public function actionHit(string $key = 'demo', int $n = 10): int
    {
        $capacity = 5;
        $rate = 1.0;

        $allowed = 0;
        $denied = 0;
        for ($i = 0; $i < $n; $i++) {
            if ($this->rateLimiter->allow($key, $capacity, $rate)) {
                $allowed++;
            } else {
                $denied++;
            }
        }

        $this->stdout(sprintf(
            "ведро=%d, пополнение=%.1f/с: из %d запросов разрешено %d, отклонено %d\n",
            $capacity,
            $rate,
            $n,
            $allowed,
            $denied
        ));

        return ExitCode::OK;
    }
}
