<?php

declare(strict_types=1);

$db = require __DIR__ . '/db.php';

$host = getenv('DB_HOST') ?: 'postgres';
$port = getenv('DB_PORT') ?: '5432';
$name = (getenv('DB_NAME') ?: 'yii2basic') . '_test';
$db['dsn'] = "pgsql:host=$host;port=$port;dbname=$name";

return $db;
