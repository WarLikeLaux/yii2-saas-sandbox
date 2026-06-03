<?php

declare(strict_types=1);

namespace app\services;

/**
 * Неизменяемый результат проверки связности инфраструктуры.
 *
 * Простой объект-значение: переносит из сервиса в вызывающий код уже готовый
 * срез состояния (результаты проб, агрегированный признак здоровья и текстовый
 * статус), не раскрывая, как именно он получен.
 */
class HealthReport
{
    /**
     * @var list<array{name: string, ok: bool, detail: string, latency_ms: int}> Результаты отдельных проверок
     */
    private $checks;

    /**
     * @var bool Признак того, что все сервисы доступны
     */
    private $healthy;

    /**
     * @var string Агрегированный текстовый статус («ok» либо «degraded»)
     */
    private $status;

    /**
     * @param list<array{name: string, ok: bool, detail: string, latency_ms: int}> $checks Результаты проверок
     * @param bool $healthy Признак того, что все сервисы доступны
     * @param string $status Агрегированный текстовый статус
     */
    public function __construct(array $checks, bool $healthy, string $status)
    {
        $this->checks = $checks;
        $this->healthy = $healthy;
        $this->status = $status;
    }

    /**
     * Возвращает результаты отдельных проверок.
     *
     * @return list<array{name: string, ok: bool, detail: string, latency_ms: int}> Результаты проверок
     */
    public function getChecks(): array
    {
        return $this->checks;
    }

    /**
     * Сообщает, доступны ли все сервисы.
     *
     * @return bool true, если все проверки прошли успешно
     */
    public function isHealthy(): bool
    {
        return $this->healthy;
    }

    /**
     * Возвращает агрегированный текстовый статус.
     *
     * @return string «ok», если все сервисы доступны, иначе «degraded»
     */
    public function getStatus(): string
    {
        return $this->status;
    }
}
