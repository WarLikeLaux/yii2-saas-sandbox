<?php

declare(strict_types=1);

namespace tests\unit\services;

use app\jobs\DemoJob;
use app\services\QueueService;
use yii\queue\Queue;

/**
 * Тесты сервиса QueueService.
 *
 * Компонент очереди подменяется моком, поэтому тесты детерминированы и не
 * требуют поднятого RabbitMQ — проверяется только контракт делегирования
 * задачи в очередь и нормализация идентификатора к строке.
 */
class QueueServiceTest extends \Codeception\Test\Unit
{
    /**
     * push() передаёт задачу в очередь и возвращает её идентификатор строкой.
     */
    public function testPushReturnsJobId(): void
    {
        $job = new DemoJob(['message' => 'привет']);

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->once())
            ->method('push')
            ->with($job)
            ->willReturn('42');

        $service = new QueueService($queue);

        verify($service->push($job))->equals('42');
    }

    /**
     * Если драйвер не вернул идентификатор, push() отдаёт null.
     */
    public function testPushReturnsNullWhenDriverHasNoId(): void
    {
        $queue = $this->createMock(Queue::class);
        $queue->method('push')->willReturn(null);

        $service = new QueueService($queue);

        verify($service->push(new DemoJob(['message' => 'x'])))->null();
    }
}
