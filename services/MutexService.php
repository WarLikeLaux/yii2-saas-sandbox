<?php

declare(strict_types=1);

namespace app\services;

use yii\mutex\Mutex;

/**
 * Сервис взаимных блокировок (mutex) поверх компонента yii2.
 *
 * Тонкая обёртка над компонентом `mutex` (в проекте — драйвер Redis,
 * `yii\redis\Mutex`): инкапсулирует прямое обращение к `Yii::$app->mutex`,
 * чтобы вызывающий код не зависел от глобального состояния и конкретного
 * драйвера. Применяется для «single-flight» — гарантии, что тяжёлую операцию
 * (например, импорт истории чата) одновременно выполняет не более одного
 * процесса, даже если задача поставлена в очередь повторно.
 *
 * Блокировка не заменяет идемпотентность обработчика: лок может истечь по TTL
 * или не захватиться, поэтому корректность данных по-прежнему обеспечивается на
 * уровне БД (например, `ON CONFLICT`), а mutex лишь экономит повторную работу.
 */
class MutexService
{
    /**
     * @var Mutex Компонент взаимных блокировок (внедряется контейнером)
     */
    private $mutex;

    /**
     * @param Mutex $mutex Компонент блокировок yii2 (конкретный драйвер задаётся конфигом)
     */
    public function __construct(Mutex $mutex)
    {
        $this->mutex = $mutex;
    }

    /**
     * Пытается захватить блокировку по ключу.
     *
     * @param string $key Имя блокировки (уникальный ключ ресурса)
     * @param int $timeout Сколько секунд ждать освобождения; 0 — не ждать
     * @return bool true, если блокировка захвачена; false, если она занята другим процессом
     */
    public function acquire(string $key, int $timeout = 0): bool
    {
        return $this->mutex->acquire($key, $timeout);
    }

    /**
     * Освобождает ранее захваченную блокировку.
     *
     * @param string $key Имя блокировки
     * @return bool true, если блокировка освобождена; false, если она не была захвачена этим процессом
     */
    public function release(string $key): bool
    {
        return $this->mutex->release($key);
    }

    /**
     * Выполняет работу под блокировкой по принципу «single-flight».
     *
     * Если блокировка свободна — захватывает её, выполняет переданную функцию и
     * гарантированно освобождает блокировку (в том числе при исключении). Если
     * блокировка уже занята другим процессом — работа НЕ выполняется.
     *
     * @param string $key Имя блокировки (уникальный ключ ресурса)
     * @param callable $work Работа, выполняемая под блокировкой
     * @param int $timeout Сколько секунд ждать освобождения; 0 — не ждать
     * @return bool true, если работа выполнена; false, если блокировка занята и работа пропущена
     * @throws \Throwable Любое исключение, выброшенное `$work` (блокировка при этом освобождается)
     */
    public function runExclusive(string $key, callable $work, int $timeout = 0): bool
    {
        if (!$this->mutex->acquire($key, $timeout)) {
            return false;
        }

        try {
            $work();
        } finally {
            $this->mutex->release($key);
        }

        return true;
    }
}
