<?php

declare(strict_types=1);

namespace app\services;

use yii\queue\JobInterface;
use yii\queue\Queue;

/**
 * Сервис постановки фоновых задач в очередь yii2-queue.
 *
 * Тонкая обёртка над компонентом очереди: инкапсулирует прямое обращение к
 * `Yii::$app->queue`, чтобы вызывающий код (контроллеры, команды, другие
 * сервисы) не зависел от глобального состояния приложения и конкретного
 * драйвера. Компонент очереди внедряется через конструктор.
 */
class QueueService implements QueueServiceInterface
{
    /**
     * @var Queue Компонент очереди yii2-queue для постановки задач
     */
    private $queue;

    /**
     * @param Queue $queue Компонент очереди (внедряется контейнером)
     */
    public function __construct(Queue $queue)
    {
        $this->queue = $queue;
    }

    /**
     * Ставит задачу в очередь на фоновую обработку.
     *
     * @param JobInterface $job Задача для постановки в очередь
     * @return string|null Идентификатор поставленной задачи или null, если драйвер его не предоставил
     */
    public function push(JobInterface $job): ?string
    {
        return $this->queue->push($job);
    }
}
