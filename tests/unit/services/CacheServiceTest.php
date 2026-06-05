<?php

declare(strict_types=1);

namespace tests\unit\services;

use app\services\CacheService;
use app\services\MutexService;
use yii\caching\CacheInterface;
use yii\mutex\Mutex;

/**
 * Тесты сервиса CacheService.
 *
 * Кеш и блокировка подменяются моками — проверяется логика cache-aside: при
 * попадании `producer` не вызывается, при промахе значение строится под
 * блокировкой и кладётся в кеш.
 */
class CacheServiceTest extends \Codeception\Test\Unit
{
    /**
     * При попадании в кеш возвращается кешированное значение, producer не зовётся.
     */
    public function testRememberReturnsCachedOnHit(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->with('k')->willReturn('cached');
        $mutex = $this->createMock(MutexService::class);

        $service = new CacheService($cache, $mutex);

        $called = false;
        $value = $service->remember('k', 60, static function () use (&$called) {
            $called = true;

            return 'fresh';
        });

        verify($value)->equals('cached');
        verify($called)->false();
    }

    /**
     * При промахе значение вычисляется под блокировкой и сохраняется в кеш.
     */
    public function testRememberBuildsValueOnMiss(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(false);
        $cache->expects($this->once())->method('set')->with('k', 'fresh', 60)->willReturn(true);

        $lowLevel = $this->createMock(Mutex::class);
        $lowLevel->method('acquire')->willReturn(true);
        $mutex = new MutexService($lowLevel);

        $service = new CacheService($cache, $mutex);

        $value = $service->remember('k', 60, static function () {
            return 'fresh';
        });

        verify($value)->equals('fresh');
    }
}
