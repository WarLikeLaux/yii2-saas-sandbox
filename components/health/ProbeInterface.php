<?php

declare(strict_types=1);

namespace app\components\health;

/**
 * Контракт пробы доступности одного инфраструктурного сервиса.
 *
 * Каждая реализация инкапсулирует проверку конкретного сервиса
 * (PostgreSQL, Redis, RabbitMQ) и получает свою зависимость через конструктор.
 * Благодаря единому контракту HealthChecker не знает о конкретных сервисах.
 */
interface ProbeInterface
{
    /**
     * Возвращает человекочитаемое имя проверяемого сервиса.
     *
     * @return string Имя сервиса (например, «PostgreSQL»)
     */
    public function name(): string;

    /**
     * Выполняет проверку доступности сервиса.
     *
     * @return string Строка с деталями успешной проверки (например, версия сервиса или результат контрольной операции)
     * @throws \Throwable Если сервис недоступен
     */
    public function check(): string;
}
