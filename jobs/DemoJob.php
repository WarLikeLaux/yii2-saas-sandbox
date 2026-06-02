<?php

namespace app\jobs;

use yii\base\BaseObject;
use yii\queue\JobInterface;
use Yii;

/**
 * Демонстрационная фоновая задача для yii2-queue (драйвер amqp_interop).
 *
 * Публикация:  ./yii queue-demo/push "текст"
 * Обработка:   ./yii queue/run   (разово)  либо  ./yii queue/listen  (демон)
 */
class DemoJob extends BaseObject implements JobInterface
{
    public $message;

    public function execute($queue)
    {
        Yii::info("DemoJob executed: {$this->message}", __METHOD__);

        // Видно при ручном запуске воркера
        fwrite(STDOUT, "[job] {$this->message}\n");
    }
}
