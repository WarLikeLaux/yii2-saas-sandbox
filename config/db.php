<?php

declare(strict_types=1);

$host = getenv('DB_HOST') ?: 'postgres';
$port = getenv('DB_PORT') ?: '5432';
$name = getenv('DB_NAME') ?: 'yii2basic';

return [
    'class' => 'yii\db\Connection',
    'dsn' => "pgsql:host=$host;port=$port;dbname=$name",
    'username' => getenv('DB_USER') ?: 'yii2',
    'password' => getenv('DB_PASSWORD') ?: 'secret',
    'charset' => 'utf8',
    'attributes' => [
        PDO::ATTR_EMULATE_PREPARES => true,
    ],
];
