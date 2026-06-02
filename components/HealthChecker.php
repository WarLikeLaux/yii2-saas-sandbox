<?php

declare(strict_types=1);

namespace app\components;

use app\components\health\ProbeInterface;

/**
 * Компонент проверки связности инфраструктуры песочницы.
 *
 * Получает набор проб (по одной на сервис) через конструктор и прогоняет их,
 * измеряя задержку каждой. Конкретные сервисы спрятаны за реализациями
 * ProbeInterface, поэтому добавление нового сервиса не требует правок этого
 * класса — достаточно зарегистрировать новую пробу в DI-контейнере.
 */
class HealthChecker implements HealthCheckerInterface
{
    /**
     * @var ProbeInterface[] Список проб инфраструктурных сервисов
     */
    private $probes;

    /**
     * @param ProbeInterface[] $probes Пробы сервисов, которые нужно проверить
     */
    public function __construct(array $probes)
    {
        $this->probes = $probes;
    }

    /**
     * Выполняет все пробы и возвращает их результаты.
     *
     * @return list<array{name: string, ok: bool, detail: string, latency_ms: int}> Результаты проверок
     */
    public function run(): array
    {
        $results = [];

        foreach ($this->probes as $probe) {
            $results[] = $this->measure($probe);
        }

        return $results;
    }

    /**
     * Проверяет, что все проверки прошли успешно.
     *
     * @param list<array{name: string, ok: bool, detail: string, latency_ms: int}> $checks Результаты проверок
     * @return bool true, если все сервисы доступны
     */
    public function isHealthy(array $checks): bool
    {
        foreach ($checks as $check) {
            if (!$check['ok']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Выполняет одну пробу и засекает время её выполнения.
     *
     * @param ProbeInterface $probe Проба сервиса
     * @return array{name: string, ok: bool, detail: string, latency_ms: int} Результат проверки
     */
    private function measure(ProbeInterface $probe): array
    {
        $start = microtime(true);

        try {
            $detail = $probe->check();
            $ok = true;
        } catch (\Throwable $e) {
            $detail = $e->getMessage();
            $ok = false;
        }

        return [
            'name' => $probe->name(),
            'ok' => $ok,
            'detail' => $detail,
            'latency_ms' => (int) round((microtime(true) - $start) * 1000),
        ];
    }
}
