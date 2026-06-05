<?php

declare(strict_types=1);

namespace tests\unit\services;

use app\services\WebhookService;
use yii\caching\CacheInterface;

/**
 * Тесты сервиса WebhookService.
 *
 * Кеш подменяется моком — проверяется проверка HMAC-подписи и отсев повторов
 * (идемпотентность) без обращения к реальному Redis.
 */
class WebhookServiceTest extends \Codeception\Test\Unit
{
    /**
     * Верная подпись принимается.
     */
    public function testVerifySignatureAcceptsValid(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $service = new WebhookService($cache, 'secret');

        $body = '{"id":"42"}';
        $signature = hash_hmac('sha256', $body, 'secret');

        verify($service->verifySignature($body, $signature))->true();
    }

    /**
     * Неверная подпись отвергается.
     */
    public function testVerifySignatureRejectsInvalid(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $service = new WebhookService($cache, 'secret');

        verify($service->verifySignature('{"id":"42"}', 'deadbeef'))->false();
    }

    /**
     * Первое событие — новое (true), повтор того же id — дубль (false).
     */
    public function testRegisterOnceDistinguishesDuplicate(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('add')->willReturnOnConsecutiveCalls(true, false);
        $service = new WebhookService($cache, 'secret');

        verify($service->registerOnce('evt-1'))->true();
        verify($service->registerOnce('evt-1'))->false();
    }
}
