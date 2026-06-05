<?php

declare(strict_types=1);

namespace tests\unit\services;

use app\services\MutexService;
use yii\mutex\Mutex;

/**
 * Тесты сервиса MutexService.
 *
 * Компонент блокировок подменяется моком, поэтому тесты детерминированы и не
 * обращаются к реальному Redis — проверяется логика «single-flight»: работа
 * выполняется только при захваченной блокировке, а блокировка всегда
 * освобождается.
 */
class MutexServiceTest extends \Codeception\Test\Unit
{
    /**
     * Когда блокировка свободна, работа выполняется и блокировка освобождается.
     */
    public function testRunExclusiveRunsWorkWhenLockAcquired(): void
    {
        $mutex = $this->createMock(Mutex::class);
        $mutex->method('acquire')->with('k', 0)->willReturn(true);
        $mutex->expects($this->once())->method('release')->with('k')->willReturn(true);

        $ran = false;
        $result = (new MutexService($mutex))->runExclusive('k', static function () use (&$ran): void {
            $ran = true;
        });

        verify($result)->true();
        verify($ran)->true();
    }

    /**
     * Когда блокировка занята, работа не выполняется и release не вызывается.
     */
    public function testRunExclusiveSkipsWorkWhenLockBusy(): void
    {
        $mutex = $this->createMock(Mutex::class);
        $mutex->method('acquire')->willReturn(false);
        $mutex->expects($this->never())->method('release');

        $ran = false;
        $result = (new MutexService($mutex))->runExclusive('k', static function () use (&$ran): void {
            $ran = true;
        });

        verify($result)->false();
        verify($ran)->false();
    }

    /**
     * Даже если работа бросает исключение, блокировка освобождается.
     */
    public function testRunExclusiveReleasesLockOnException(): void
    {
        $mutex = $this->createMock(Mutex::class);
        $mutex->method('acquire')->willReturn(true);
        $mutex->expects($this->once())->method('release')->with('k');

        $service = new MutexService($mutex);

        $caught = false;
        try {
            $service->runExclusive('k', static function (): void {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException $e) {
            $caught = true;
        }

        verify($caught)->true();
    }
}
