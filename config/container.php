<?php

declare(strict_types=1);

use app\components\health\PostgresProbe;
use app\components\health\RabbitProbe;
use app\components\health\RedisProbe;
use app\components\HealthChecker;
use app\components\HealthCheckerInterface;
use app\services\HealthService;
use app\services\HealthServiceInterface;
use app\services\QueueService;
use app\services\QueueServiceInterface;
use yii\di\Container;

return [
    'definitions' => [
        PostgresProbe::class => function () {
            return new PostgresProbe(Yii::$app->db);
        },
        RedisProbe::class => function () {
            return new RedisProbe(Yii::$app->redis);
        },
        RabbitProbe::class => function () {
            return new RabbitProbe(Yii::$app->queue);
        },
        HealthCheckerInterface::class => function (Container $container) {
            return new HealthChecker([
                $container->get(PostgresProbe::class),
                $container->get(RedisProbe::class),
                $container->get(RabbitProbe::class),
            ]);
        },
        HealthServiceInterface::class => function (Container $container) {
            return new HealthService($container->get(HealthCheckerInterface::class));
        },
        QueueServiceInterface::class => function () {
            return new QueueService(Yii::$app->queue);
        },
    ],
];
