<?php

declare(strict_types=1);

namespace app\commands;

use app\jobs\DemoJob;
use Yii;
use yii\console\Application;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\queue\amqp_interop\Queue;

/**
 * Демонстрация постановки фоновой задачи в yii2-queue.
 *
 * Поставить:  ./yii queue-demo/push "текст"
 * Обработать: ./yii queue/listen (демон)
 */
class QueueDemoController extends Controller
{
    /**
     * Публикует тестовое сообщение в очередь sandbox_jobs.
     *
     * @param string $message Тело сообщения
     * @return int Код возврата (0 — успех)
     */
    public function actionPush(string $message = 'hello from yii2-queue'): int
    {
        $app = Yii::$app;
        assert($app instanceof Application);

        /** @var Queue $queue */
        $queue = $app->get('queue');
        $id = (string) $queue->push(new DemoJob(['message' => $message]));

        $this->stdout("Pushed job #{$id} to queue 'sandbox_jobs': {$message}\n");

        return ExitCode::OK;
    }
}
