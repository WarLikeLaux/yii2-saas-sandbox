<?php

use yii\queue\amqp_interop\Queue;

return [
    'class' => Queue::class,
    'driver' => Queue::ENQUEUE_AMQP_LIB, // транспорт на базе php-amqplib, без PHP-расширения amqp
    'host' => getenv('RABBITMQ_HOST') ?: 'rabbitmq',
    'port' => getenv('RABBITMQ_PORT') ?: 5672,
    'user' => getenv('RABBITMQ_USER') ?: 'guest',
    'password' => getenv('RABBITMQ_PASSWORD') ?: 'guest',
    'vhost' => getenv('RABBITMQ_VHOST') ?: '/',
    'queueName' => 'sandbox_jobs',
    'exchangeName' => 'sandbox_jobs',
];
