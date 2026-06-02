<?php

/** @var yii\web\View $this */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'SaaS Sandbox';

$stackBadges = [
    'PHP 7.4',
    'Yii2',
    'PostgreSQL 16',
    'Redis 7',
    'RabbitMQ 3',
    'Nginx',
];

$services = [
    [
        'icon' => 'bi-database',
        'title' => 'PostgreSQL 16',
        'text' => 'Основное хранилище данных. Реляционная БД для прикладной модели песочницы.',
        'accent' => 'text-primary',
    ],
    [
        'icon' => 'bi-lightning-charge',
        'title' => 'Redis 7',
        'text' => 'Кэш Yii по сокетам и быстрое хранилище. Подключение через yii\\redis\\Cache.',
        'accent' => 'text-danger',
    ],
    [
        'icon' => 'bi-arrow-left-right',
        'title' => 'RabbitMQ 3',
        'text' => 'Брокер очередей для yii2-queue, драйвер amqp_interop поверх RabbitMQ.',
        'accent' => 'text-warning',
    ],
];

$quality = [
    'declare(strict_types=1) обязателен во всех файлах проекта.',
    'PHPStan level 6 + phpstan-strict-rules для строгой статической проверки.',
    'PHP-CS-Fixer — единый стиль по PSR-12.',
    'Rector — автоматический рефакторинг с таргетом PHP 7.4.',
    'composer audit — контроль уязвимостей в зависимостях.',
    'GitHub Actions CI запускается на каждый push.',
];
?>
<div class="site-index">

    <section class="text-center py-5 my-3">
        <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis mb-3 px-3 py-2">
            <i class="bi bi-stars me-1"></i>SaaS бэкенд-песочница
        </span>
        <h1 class="display-3 fw-bold mb-3"><?= Html::encode($this->title) ?></h1>
        <p class="lead text-secondary mb-2">Песочница для прототипирования бэкенда SaaS-сервиса</p>
        <p class="text-muted font-monospace mb-4">PHP 7.4 &middot; Yii2 Basic &middot; Docker</p>

        <div class="d-flex flex-wrap justify-content-center gap-2 mb-4">
            <?php foreach ($stackBadges as $badge): ?>
                <span class="badge rounded-pill bg-dark px-3 py-2 fs-6 fw-normal"><?= Html::encode($badge) ?></span>
            <?php endforeach; ?>
        </div>

        <?= Html::a(
            'Проверить состояние сервисов <i class="bi bi-arrow-right ms-1"></i>',
            ['/health'],
            ['class' => 'btn btn-primary btn-lg px-4 shadow-sm', 'encode' => false]
        ) ?>
    </section>

    <section class="py-4">
        <h2 class="h4 fw-semibold mb-4 text-center">Backing-сервисы</h2>
        <div class="row g-4">
            <?php foreach ($services as $service): ?>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm rounded-3">
                        <div class="card-body p-4">
                            <div class="fs-1 mb-2 <?= Html::encode($service['accent']) ?>">
                                <i class="bi <?= Html::encode($service['icon']) ?>"></i>
                            </div>
                            <h3 class="h5 card-title fw-semibold"><?= Html::encode($service['title']) ?></h3>
                            <p class="card-text text-secondary mb-0"><?= Html::encode($service['text']) ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="py-4 my-2">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-body p-4 p-md-5">
                <div class="d-flex align-items-center mb-4">
                    <span class="fs-2 text-success me-3"><i class="bi bi-shield-check"></i></span>
                    <h2 class="h4 fw-semibold m-0">Качество кода</h2>
                </div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($quality as $item): ?>
                        <li class="list-group-item d-flex align-items-start gap-2 px-0">
                            <i class="bi bi-check2-circle text-success mt-1"></i>
                            <span><?= Html::encode($item) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </section>

    <section class="text-center py-4 mb-3">
        <p class="text-secondary mb-3">Хотите убедиться, что весь стек на связи?</p>
        <?= Html::a(
            'Проверить состояние сервисов <i class="bi bi-arrow-right ms-1"></i>',
            Url::to(['/health']),
            ['class' => 'btn btn-primary btn-lg px-4 shadow-sm', 'encode' => false]
        ) ?>
    </section>

</div>
