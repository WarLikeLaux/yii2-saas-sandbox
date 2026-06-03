<?php

declare(strict_types=1);

namespace app\commands;

use app\jobs\DemoJob;
use app\services\QueueServiceInterface;
use yii\base\Module;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Демонстрация постановки фоновой задачи в yii2-queue.
 *
 * ```
 * Поставить:  ./yii queue-demo/push "текст"
 * Обработать: ./yii queue/listen (демон)
 * ```
 */
class QueueDemoController extends Controller
{
    /**
     * @var QueueServiceInterface Сервис постановки фоновых задач в очередь
     */
    private $queueService;

    /**
     * @param string $id Идентификатор контроллера
     * @param Module $module Модуль, которому принадлежит контроллер
     * @param QueueServiceInterface $queueService Сервис постановки задач (внедряется контейнером)
     * @param array<string, mixed> $config Дополнительная конфигурация
     */
    public function __construct(string $id, Module $module, QueueServiceInterface $queueService, array $config = [])
    {
        $this->queueService = $queueService;
        parent::__construct($id, $module, $config);
    }

    /**
     * Публикует тестовое сообщение в очередь sandbox_jobs.
     *
     * @param string $message Тело сообщения
     * @return int Код возврата (0 — успех)
     */
    public function actionPush(string $message = 'hello from yii2-queue'): int
    {
        $id = $this->queueService->push(new DemoJob(['message' => $message]));

        $this->stdout("Pushed job #{$id} to queue 'sandbox_jobs': {$message}\n");

        return ExitCode::OK;
    }
}
