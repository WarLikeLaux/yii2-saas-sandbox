<?php

declare(strict_types=1);

namespace app\services;

use app\components\CorrelationContext;
use Sentry\State\Scope;
use Throwable;

/**
 * Фасад отправки ошибок и событий в Sentry.
 *
 * Sentry — внешний сервис отлова ошибок: вместо «гриппа по логам» он собирает
 * исключения, группирует их, показывает стектрейс и контекст, шлёт алерты.
 * Здесь SDK инициализируется по DSN из параметров приложения; если DSN пуст
 * (как в песочнице по умолчанию), фасад работает как no-op — ничего никуда не
 * уходит, и код вызывающих не ломается.
 *
 * К каждому событию привязывается `correlation_id` ({@see CorrelationContext}),
 * поэтому ошибку в Sentry можно сопоставить с цепочкой логов в Kibana.
 */
class SentryService
{
    /**
     * @var CorrelationContext Контекст корреляции для тегирования событий
     */
    private $correlation;

    /**
     * @var bool Включён ли Sentry (задан ли DSN)
     */
    private $enabled;

    /**
     * @param string $dsn DSN Sentry (пустая строка — Sentry отключён)
     * @param CorrelationContext $correlation Контекст корреляции
     */
    public function __construct(string $dsn, CorrelationContext $correlation)
    {
        $this->correlation = $correlation;
        $this->enabled = $dsn !== '';

        if ($this->enabled) {
            \Sentry\init(['dsn' => $dsn]);
        }
    }

    /**
     * Включён ли Sentry (задан ли DSN).
     *
     * @return bool true, если события реально отправляются
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Отправляет исключение в Sentry (no-op, если Sentry отключён).
     *
     * @param Throwable $exception Исключение для отправки
     * @return void
     */
    public function captureException(Throwable $exception): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->tagCorrelation();
        \Sentry\captureException($exception);
    }

    /**
     * Отправляет текстовое событие в Sentry (no-op, если Sentry отключён).
     *
     * @param string $message Текст события
     * @return void
     */
    public function captureMessage(string $message): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->tagCorrelation();
        \Sentry\captureMessage($message);
    }

    /**
     * Привязывает к области видимости Sentry текущий correlation id.
     *
     * @return void
     */
    private function tagCorrelation(): void
    {
        $correlationId = $this->correlation->get();
        if ($correlationId === '') {
            return;
        }

        \Sentry\configureScope(static function (Scope $scope) use ($correlationId): void {
            $scope->setTag('correlation_id', $correlationId);
        });
    }
}
