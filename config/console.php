<?php

declare(strict_types=1);

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';
$redis = require __DIR__ . '/redis.php';
$queue = require __DIR__ . '/queue.php';
$mutex = require __DIR__ . '/mutex.php';

$config = [
    'id' => 'basic-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log', 'queue'],
    'container' => require __DIR__ . '/container.php',
    'controllerNamespace' => 'app\commands',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm' => '@vendor/npm-asset',
        '@tests' => '@app/tests',
    ],
    'components' => [
        'redis' => $redis,
        'queue' => $queue,
        'mutex' => $mutex,
        'cache' => [
            'class' => 'yii\redis\Cache',
        ],
        'log' => [
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
                [
                    'class' => app\components\JsonLogTarget::class,
                    'logFile' => '@runtime/logs/app.json.log',
                    'levels' => ['error', 'warning', 'info'],
                    'except' => ['yii\db\*'],
                    'logVars' => [],
                    'correlationIdResolver' => static function () {
                        return Yii::$container->get(app\components\CorrelationContext::class)->get();
                    },
                ],
                [
                    'class' => app\components\SentryTarget::class,
                    'levels' => ['error'],
                    'sentryResolver' => static function () {
                        return Yii::$container->get(app\services\SentryService::class);
                    },
                ],
            ],
        ],
        'db' => $db,
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
    ];
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
    ];
}

return $config;
