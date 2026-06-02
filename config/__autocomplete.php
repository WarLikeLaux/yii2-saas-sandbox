<?php

declare(strict_types=1);

/**
 * Файл существует только для автодополнения в IDE (PhpStorm/Netbeans/...).
 * Нигде не подключается и не исполняется. Здесь описываются кастомные
 * компоненты приложения, чтобы IDE подсказывала их типы у `Yii::$app->...`.
 */
class Yii
{
    /**
     * @var \yii\web\Application|\yii\console\Application|__Application
     */
    public static $app;
}

/**
 * @property \yii\db\Connection $db
 * @property \yii\redis\Connection $redis
 * @property \yii\redis\Cache $cache
 * @property \yii\queue\amqp_interop\Queue $queue
 */
class __Application
{
}
