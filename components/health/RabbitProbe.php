<?php

declare(strict_types=1);

namespace app\components\health;

use yii\queue\amqp_interop\Queue;

/**
 * Проба доступности RabbitMQ.
 *
 * Устанавливает соединение с брокером через контекст yii2-queue (amqp_interop).
 */
class RabbitProbe implements ProbeInterface
{
    /**
     * @var Queue Очередь yii2-queue поверх RabbitMQ
     */
    private $queue;

    /**
     * @param Queue $queue Очередь yii2-queue (внедряется контейнером)
     */
    public function __construct(Queue $queue)
    {
        $this->queue = $queue;
    }

    /**
     * Возвращает имя сервиса.
     *
     * @return string Имя сервиса
     */
    public function name(): string
    {
        return 'RabbitMQ';
    }

    /**
     * Открывает временную очередь в контексте брокера и закрывает соединение.
     *
     * @return string Сообщение об успешном установлении соединения
     * @throws \Throwable Если соединение с брокером не удалось
     */
    public function check(): string
    {
        $context = $this->queue->getContext();
        $context->createTemporaryQueue();
        $context->close();

        return 'yii2-queue/amqp_interop context established';
    }
}
