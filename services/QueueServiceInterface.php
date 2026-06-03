<?php

declare(strict_types=1);

namespace app\services;

use yii\queue\JobInterface;

/**
 * Контракт сервиса постановки фоновых задач в очередь.
 *
 * Скрывает за абстракцией конкретный драйвер yii2-queue, поэтому контроллеры,
 * команды и другие сервисы зависят от интерфейса, а не от компонента очереди.
 * Это делает постановку задач переиспользуемой и легко подменяемой в тестах.
 */
interface QueueServiceInterface
{
    /**
     * Ставит задачу в очередь на фоновую обработку.
     *
     * @param JobInterface $job Задача для постановки в очередь
     * @return string|null Идентификатор поставленной задачи или null, если драйвер его не предоставил
     */
    public function push(JobInterface $job): ?string;
}
