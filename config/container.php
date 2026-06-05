<?php

declare(strict_types=1);

use app\components\health\PostgresProbe;
use app\components\health\RabbitProbe;
use app\components\health\RedisProbe;
use app\components\HealthChecker;
use app\helpers\DbHelper;
use app\helpers\RequestParamHelper;
use app\services\CacheService;
use app\services\HealthService;
use app\services\MessageFeed;
use app\services\MessagePageService;
use app\services\MessageSeeder;
use app\services\MessageStatusService;
use app\services\MutexService;
use app\services\QueueService;
use app\services\RateLimiterService;
use app\services\WebhookService;
use yii\db\Connection;
use yii\di\Container;

return [
    'singletons' => [
        Connection::class => require __DIR__ . '/db.php',
    ],
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
        MutexService::class => function () {
            return new MutexService(Yii::$app->mutex);
        },
        CacheService::class => function (Container $container) {
            return new CacheService(Yii::$app->cache, $container->get(MutexService::class));
        },
        RateLimiterService::class => function () {
            return new RateLimiterService(Yii::$app->redis);
        },
        WebhookService::class => function () {
            $secret = isset(Yii::$app->params['webhookSecret']) ? (string) Yii::$app->params['webhookSecret'] : '';

            return new WebhookService(Yii::$app->cache, $secret);
        },
        MessageSeeder::class => function () {
            return new MessageSeeder(Yii::$app->db);
        },
        MessageStatusService::class => function () {
            return new MessageStatusService(Yii::$app->db);
        },
        MessageFeed::class => function (Container $container) {
            return new MessageFeed(Yii::$app->db, $container->get(DbHelper::class));
        },
        MessagePageService::class => function (Container $container) {
            return new MessagePageService(
                $container->get(MessageFeed::class),
                $container->get(RequestParamHelper::class)
            );
        },
        RequestParamHelper::class => function () {
            return new RequestParamHelper(Yii::$app->request);
        },
    ],
];
