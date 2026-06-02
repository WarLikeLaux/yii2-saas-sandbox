<?php

return [
    'class' => 'yii\redis\Connection',
    'hostname' => getenv('REDIS_HOST') ?: 'redis',
    'port' => getenv('REDIS_PORT') ?: 6379,
    'database' => 0,
];
