<?php

declare(strict_types=1);

namespace app\services;

use yii\caching\CacheInterface;

/**
 * Сервис приёма входящих вебхуков от внешних площадок.
 *
 * Решает две задачи надёжного входа: проверку подлинности запроса (подпись
 * HMAC) и защиту от повторной обработки (идемпотентность по идентификатору
 * события). Площадки доставляют вебхуки «хотя бы один раз» и при сбое сети
 * повторяют их, поэтому один и тот же вебхук может прийти несколько раз —
 * {@see registerOnce()} распознаёт повтор и не даёт обработать его дважды.
 */
class WebhookService
{
    /**
     * Срок (в секундах), в течение которого помним обработанные события для
     * отсева повторов.
     */
    private const DEDUP_TTL = 86400;

    /**
     * @var CacheInterface Хранилище для отсева повторов (в проекте — Redis)
     */
    private $cache;

    /**
     * @var string Секрет для проверки HMAC-подписи вебхуков
     */
    private $secret;

    /**
     * @param CacheInterface $cache Компонент кеша (внедряется контейнером)
     * @param string $secret Секрет для проверки подписи (из параметров приложения)
     */
    public function __construct(CacheInterface $cache, string $secret)
    {
        $this->cache = $cache;
        $this->secret = $secret;
    }

    /**
     * Проверяет HMAC-подпись тела запроса в постоянном времени.
     *
     * Площадка подписывает тело запроса тем же секретом (`HMAC-SHA256`) и шлёт
     * подпись в заголовке. Сравнение делаем через `hash_equals`, чтобы не
     * допустить атаки по времени (timing attack).
     *
     * @param string $rawBody Сырое тело запроса (как пришло, без перекодировок)
     * @param string $signature Подпись из заголовка запроса (hex)
     * @return bool true, если подпись верна
     */
    public function verifySignature(string $rawBody, string $signature): bool
    {
        $expected = hash_hmac('sha256', $rawBody, $this->secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Регистрирует событие как обработанное; распознаёт повтор.
     *
     * Опирается на атомарную операцию «добавить, только если ключа ещё нет»:
     * первый вызов для данного `$eventId` вернёт true, любой повторный в течение
     * TTL — false. Так повторная доставка вебхука не приведёт к двойной
     * обработке.
     *
     * @param string $eventId Идентификатор события от площадки (ключ идемпотентности)
     * @return bool true, если событие новое; false, если это повтор
     */
    public function registerOnce(string $eventId): bool
    {
        return $this->cache->add('webhook:seen:' . $eventId, 1, self::DEDUP_TTL);
    }
}
