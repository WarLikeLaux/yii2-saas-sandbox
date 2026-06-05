<?php

declare(strict_types=1);

namespace tests\unit\services;

use app\services\RateLimiterService;
use yii\redis\Connection;

/**
 * Тесты сервиса RateLimiterService.
 *
 * Соединение с Redis подменяется моком — проверяется, что результат Lua-скрипта
 * token bucket корректно превращается в «разрешено / отклонено».
 */
class RateLimiterServiceTest extends \Codeception\Test\Unit
{
    /**
     * Скрипт вернул 1 — запрос разрешён.
     */
    public function testAllowReturnsTrueWhenScriptAllows(): void
    {
        $redis = $this->createMock(Connection::class);
        $redis->method('executeCommand')->willReturn(1);

        verify((new RateLimiterService($redis))->allow('k', 5, 1.0))->true();
    }

    /**
     * Скрипт вернул 0 — запрос отклонён.
     */
    public function testAllowReturnsFalseWhenScriptDenies(): void
    {
        $redis = $this->createMock(Connection::class);
        $redis->method('executeCommand')->willReturn(0);

        verify((new RateLimiterService($redis))->allow('k', 5, 1.0))->false();
    }
}
