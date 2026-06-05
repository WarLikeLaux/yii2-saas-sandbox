<?php

declare(strict_types=1);

return [
    'webhookSecret' => getenv('WEBHOOK_SECRET') ?: 'dev-secret-change-me',
];
