<?php

declare(strict_types=1);

namespace app\services;

use yii\caching\CacheInterface;

/**
 * Сервис кеширования по схеме «cache-aside» с защитой от «cache stampede».
 *
 * Инкапсулирует работу с компонентом кеша (в проекте — Redis). Главная ценность
 * — метод {@see remember()}: при промахе он перестраивает значение под взаимной
 * блокировкой ({@see MutexService}), поэтому при одновременном промахе у сотни
 * запросов дорогой `producer` выполняется ОДИН раз (single-flight), а не сотню
 * раз параллельно. Это предотвращает «cache stampede» — лавину одинаковых
 * тяжёлых пересчётов, которая способна положить базу в момент истечения ключа.
 */
class CacheService
{
    /**
     * @var CacheInterface Хранилище кеша (в проекте — Redis)
     */
    private $cache;

    /**
     * @var MutexService Сервис блокировок для single-flight перестроения значения
     */
    private $mutex;

    /**
     * @param CacheInterface $cache Компонент кеша (внедряется контейнером)
     * @param MutexService $mutex Сервис взаимных блокировок (внедряется контейнером)
     */
    public function __construct(CacheInterface $cache, MutexService $mutex)
    {
        $this->cache = $cache;
        $this->mutex = $mutex;
    }

    /**
     * Возвращает значение из кеша, а при промахе вычисляет его и кладёт в кеш.
     *
     * При промахе перестроение выполняется под блокировкой по ключу: только один
     * процесс вызывает `$producer`, остальные дожидаются готового значения в
     * кеше. Так дорогой пересчёт не дублируется (защита от cache stampede).
     *
     * @param string $key Ключ кеша
     * @param int $ttl Время жизни значения в секундах
     * @param callable $producer Функция, вычисляющая значение при промахе
     * @return mixed Значение из кеша или вычисленное `$producer`
     * @throws \Throwable Любое исключение, выброшенное `$producer`
     */
    public function remember(string $key, int $ttl, callable $producer)
    {
        $value = $this->cache->get($key);
        if ($value !== false) {
            return $value;
        }

        $holder = ['value' => null, 'built' => false];

        $acquired = $this->mutex->runExclusive('cache:build:' . $key, function () use ($key, $ttl, $producer, &$holder): void {
            $cached = $this->cache->get($key);
            if ($cached !== false) {
                $holder = ['value' => $cached, 'built' => true];

                return;
            }

            $fresh = $producer();
            $this->cache->set($key, $fresh, $ttl);
            $holder = ['value' => $fresh, 'built' => true];
        });

        if ($acquired && $holder['built']) {
            return $holder['value'];
        }

        $value = $this->cache->get($key);
        if ($value !== false) {
            return $value;
        }

        return $producer();
    }

    /**
     * Удаляет значение из кеша (инвалидация).
     *
     * @param string $key Ключ кеша
     * @return bool true, если значение удалено
     */
    public function invalidate(string $key): bool
    {
        return $this->cache->delete($key);
    }
}
