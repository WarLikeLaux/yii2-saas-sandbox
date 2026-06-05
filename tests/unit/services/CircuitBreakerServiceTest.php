<?php

declare(strict_types=1);

namespace tests\unit\services;

use app\services\CircuitBreakerService;
use yii\redis\Connection;

/**
 * Тесты сервиса CircuitBreakerService.
 *
 * Соединение с Redis подменяется моком — проверяется логика состояний: цепь
 * доступна, пока не разомкнута; размыкается по достижении порога отказов.
 */
class CircuitBreakerServiceTest extends \Codeception\Test\Unit
{
    /**
     * Без записи об открытии цепь замкнута — вызовы доступны.
     */
    public function testAvailableWhenClosed(): void
    {
        $redis = $this->createMock(Connection::class);
        $redis->method('executeCommand')->willReturn(false);

        verify((new CircuitBreakerService($redis))->isAvailable('api'))->true();
    }

    /**
     * Пока не истёк cooldown открытия, цепь разомкнута — вызовы недоступны.
     */
    public function testUnavailableWhenOpen(): void
    {
        $redis = $this->createMock(Connection::class);
        $redis->method('executeCommand')->willReturn((string) (time() + 60));

        verify((new CircuitBreakerService($redis))->isAvailable('api'))->false();
    }

    /**
     * Отказ ниже порога цепь не размыкает (SETEX не вызывается).
     */
    public function testFailureBelowThresholdDoesNotOpen(): void
    {
        $commands = [];
        $redis = $this->createMock(Connection::class);
        $redis->method('executeCommand')->willReturnCallback(static function ($name) use (&$commands) {
            $commands[] = $name;

            return $name === 'INCR' ? 2 : null;
        });

        (new CircuitBreakerService($redis))->recordFailure('api');

        verify(in_array('SETEX', $commands, true))->false();
    }

    /**
     * Достижение порога отказов размыкает цепь (вызывается SETEX открытия).
     */
    public function testFailureAtThresholdOpens(): void
    {
        $commands = [];
        $redis = $this->createMock(Connection::class);
        $redis->method('executeCommand')->willReturnCallback(static function ($name) use (&$commands) {
            $commands[] = $name;

            return $name === 'INCR' ? 5 : null;
        });

        (new CircuitBreakerService($redis))->recordFailure('api');

        verify(in_array('SETEX', $commands, true))->true();
    }
}
