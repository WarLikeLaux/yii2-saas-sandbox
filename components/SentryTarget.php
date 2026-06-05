<?php

declare(strict_types=1);

namespace app\components;

use app\services\SentryService;
use Throwable;
use yii\log\Target;

/**
 * Лог-таргет, пересылающий ошибки в Sentry.
 *
 * Подключается в `log` с уровнем `error`: исключения, которые Yii логирует через
 * error-handler, попадают сюда и уходят в Sentry как `captureException`
 * (с полным стектрейсом), а строковые ошибки — как `captureMessage`. Если Sentry
 * отключён (нет DSN), вызовы фасада — no-op, и таргет ничего не делает.
 */
class SentryTarget extends Target
{
    /**
     * @var callable|null Резолвер фасада Sentry (возвращает {@see SentryService})
     */
    public $sentryResolver;

    /**
     * Пересылает накопленные сообщения уровня ошибки в Sentry.
     *
     * @return void
     */
    public function export()
    {
        if (!is_callable($this->sentryResolver)) {
            return;
        }

        $sentry = call_user_func($this->sentryResolver);
        if (!$sentry instanceof SentryService) {
            return;
        }

        foreach ($this->messages as $message) {
            $payload = is_array($message) && array_key_exists(0, $message) ? $message[0] : null;
            if ($payload instanceof Throwable) {
                $sentry->captureException($payload);
            } elseif (is_string($payload)) {
                $sentry->captureMessage($payload);
            }
        }
    }
}
