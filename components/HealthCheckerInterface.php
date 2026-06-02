<?php

declare(strict_types=1);

namespace app\components;

/**
 * Контракт компонента проверки связности инфраструктуры.
 *
 * Позволяет контроллерам зависеть от абстракции, а не от конкретной
 * реализации — конкретный набор проверок подставляет DI-контейнер.
 */
interface HealthCheckerInterface
{
    /**
     * Выполняет все настроенные пробы и возвращает их результаты.
     *
     * @return list<array{name: string, ok: bool, detail: string, latency_ms: int}> Результаты проверок
     */
    public function run(): array;

    /**
     * Проверяет, что все проверки прошли успешно.
     *
     * @param list<array{name: string, ok: bool, detail: string, latency_ms: int}> $checks Результаты проверок
     * @return bool true, если все сервисы доступны
     */
    public function isHealthy(array $checks): bool;
}
