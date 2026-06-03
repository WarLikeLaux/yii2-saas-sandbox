<?php

declare(strict_types=1);

use app\components\health\PostgresProbe;
use app\components\health\RabbitProbe;
use app\components\health\RedisProbe;
use app\components\HealthChecker;
use app\services\HealthService;
use app\services\MessageSeeder;
use app\services\QueueService;
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
        HealthChecker::class => function (Container $container) {
            return new HealthChecker([
                $container->get(PostgresProbe::class),
                $container->get(RedisProbe::class),
                $container->get(RabbitProbe::class),
            ]);
        },
        HealthService::class => function (Container $container) {
            return new HealthService($container->get(HealthChecker::class));
        },
        QueueService::class => function () {
            return new QueueService(Yii::$app->queue);
        },
        MessageSeeder::class => function () {
            return new MessageSeeder(Yii::$app->db);
        },
    ],
];
