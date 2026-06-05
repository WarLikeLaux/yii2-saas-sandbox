<?php

declare(strict_types=1);

namespace app\commands;

use app\services\SentryService;
use yii\base\Module;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Демонстрация интеграции с Sentry.
 *
 * Если задан `SENTRY_DSN`, отправляет тестовое событие; иначе сообщает, что
 * Sentry отключён (события никуда не уходят).
 *
 * ```
 * ./yii sentry-demo/test
 * ```
 */
class SentryDemoController extends Controller
{
    /**
     * @var SentryService Фасад Sentry
     */
    private $sentry;

    /**
     * @param string $id Идентификатор контроллера
     * @param Module $module Модуль, которому принадлежит контроллер
     * @param SentryService $sentry Фасад Sentry (внедряется контейнером)
     * @param array<string, mixed> $config Дополнительная конфигурация
     */
    public function __construct(string $id, Module $module, SentryService $sentry, array $config = [])
    {
        $this->sentry = $sentry;
        parent::__construct($id, $module, $config);
    }

    /**
     * Отправляет тестовое событие в Sentry (или сообщает, что он отключён).
     *
     * @return int Код возврата (0 — успех)
     */
    public function actionTest(): int
    {
        if (!$this->sentry->isEnabled()) {
            $this->stdout("Sentry отключён (SENTRY_DSN пуст) — события никуда не уходят. Задайте SENTRY_DSN для реальной отправки.\n");

            return ExitCode::OK;
        }

        $this->sentry->captureMessage('Тестовое событие из sentry-demo');
        $this->stdout("Тестовое событие отправлено в Sentry\n");

        return ExitCode::OK;
    }
}
