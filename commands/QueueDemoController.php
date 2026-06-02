<?php

namespace app\commands;

use app\jobs\DemoJob;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Демонстрация постановки фоновой задачи в yii2-queue.
 *
 * Поставить:  ./yii queue-demo/push "текст"
 * Обработать: ./yii queue/run  (разово, до опустошения)
 *             ./yii queue/listen (демон)
 *             ./yii queue/info (состояние очереди)
 */
class QueueDemoController extends Controller
{
    public function actionPush($message = 'hello from yii2-queue')
    {
        $id = Yii::$app->queue->push(new DemoJob(['message' => $message]));

        $this->stdout("Pushed job #{$id} to queue 'sandbox_jobs': {$message}\n");

        return ExitCode::OK;
    }
}
