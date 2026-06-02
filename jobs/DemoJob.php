<?php

declare(strict_types=1);

namespace app\jobs;

use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

/**
 * Демонстрационная фоновая задача для yii2-queue (драйвер amqp_interop).
 *
 * Публикация:  ./yii queue-demo/push "текст"
 * Обработка:   ./yii queue/listen (демон)
 */
class DemoJob extends BaseObject implements JobInterface
{
    /**
     * @var string Тело сообщения
     */
    public $message;

    /**
     * Обрабатывает задачу: пишет тело в лог и в STDOUT.
     *
     * @param \yii\queue\Queue $queue Очередь, из которой пришла задача
     * @return void
     */
    public function execute($queue)
    {
        Yii::info("DemoJob executed: {$this->message}", __METHOD__);

        fwrite(STDOUT, "[job] {$this->message}\n");
    }
}
