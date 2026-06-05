<?php

declare(strict_types=1);

namespace tests\unit\services;

use app\components\CorrelationContext;
use app\services\SentryService;

/**
 * Тесты фасада Sentry.
 *
 * Проверяется поведение без DSN: Sentry отключён, а вызовы capture-методов —
 * безопасный no-op (ничего не отправляют и не падают).
 */
class SentryServiceTest extends \Codeception\Test\Unit
{
    /**
     * Без DSN Sentry отключён.
     */
    public function testDisabledWithoutDsn(): void
    {
        $service = new SentryService('', new CorrelationContext());

        verify($service->isEnabled())->false();
    }

    /**
     * Без DSN capture-методы — безопасный no-op.
     */
    public function testCaptureIsNoopWhenDisabled(): void
    {
        $service = new SentryService('', new CorrelationContext());

        $service->captureMessage('тест');
        $service->captureException(new \RuntimeException('тест'));

        verify($service->isEnabled())->false();
    }
}
