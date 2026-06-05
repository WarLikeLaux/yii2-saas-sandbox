<?php

declare(strict_types=1);

namespace app\services;

use yii\redis\Connection;

/**
 * Предохранитель (circuit breaker) для вызовов нестабильных внешних API.
 *
 * Когда внешний сервис (площадка) начинает массово отказывать, продолжать его
 * долбить вредно: повторы копят зависшие воркеры и мешают сервису встать. Идея
 * предохранителя — после серии отказов «разомкнуть цепь»: какое-то время
 * (`OPEN_COOLDOWN`) сразу отклонять вызовы, не трогая лежащий сервис, а потом
 * снова пробовать.
 *
 * Состояния:
 *  - **closed** — норма, вызовы проходят, отказы считаются;
 *  - **open** — после `FAILURE_THRESHOLD` отказов: `isAvailable()` отдаёт false
 *    в течение `OPEN_COOLDOWN` секунд (быстрый отказ, сервис не трогаем);
 *  - после остывания цепь снова замкнута и пробует вызовы (про half-open —
 *    оговорка в уроке).
 *
 * Состояние хранится в Redis, поэтому общее для всех воркеров. Дополняет повторы
 * с backoff (урок по retry) и ограничитель частоты ({@see RateLimiterService}).
 */
class CircuitBreakerService
{
    /**
     * Сколько отказов подряд размыкают цепь.
     */
    private const FAILURE_THRESHOLD = 5;

    /**
     * На сколько секунд цепь остаётся разомкнутой (быстрый отказ).
     */
    private const OPEN_COOLDOWN = 30;

    /**
     * @var Connection Соединение с Redis (внедряется контейнером)
     */
    private $redis;

    /**
     * @param Connection $redis Соединение с Redis
     */
    public function __construct(Connection $redis)
    {
        $this->redis = $redis;
    }

    /**
     * Доступен ли вызов сейчас (цепь не разомкнута).
     *
     * @param string $key Имя защищаемого ресурса (например, внешнего API)
     * @return bool true, если вызывать можно; false, если цепь разомкнута
     */
    public function isAvailable(string $key): bool
    {
        $openUntil = intval($this->redis->executeCommand('GET', ['cb:open:' . $key]));

        return $openUntil <= time();
    }

    /**
     * Фиксирует успешный вызов: сбрасывает счётчик отказов и замыкает цепь.
     *
     * @param string $key Имя защищаемого ресурса
     * @return void
     */
    public function recordSuccess(string $key): void
    {
        $this->redis->executeCommand('DEL', ['cb:fail:' . $key, 'cb:open:' . $key]);
    }

    /**
     * Фиксирует отказ: при достижении порога размыкает цепь на cooldown.
     *
     * @param string $key Имя защищаемого ресурса
     * @return void
     */
    public function recordFailure(string $key): void
    {
        $failures = intval($this->redis->executeCommand('INCR', ['cb:fail:' . $key]));
        $this->redis->executeCommand('EXPIRE', ['cb:fail:' . $key, self::OPEN_COOLDOWN]);

        if ($failures >= self::FAILURE_THRESHOLD) {
            $this->redis->executeCommand('SETEX', [
                'cb:open:' . $key,
                self::OPEN_COOLDOWN,
                (string) (time() + self::OPEN_COOLDOWN),
            ]);
        }
    }
}
