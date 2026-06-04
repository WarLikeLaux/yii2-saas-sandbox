<?php

/**
 * @var yii\web\View $this
 * @var bool $healthy
 * @var array[] $checks
 */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Состояние сервисов';

$meta = [
    'PostgreSQL' => ['icon' => 'bi-database', 'accent' => 'text-primary'],
    'Redis' => ['icon' => 'bi-lightning-charge', 'accent' => 'text-danger'],
    'RabbitMQ' => ['icon' => 'bi-arrow-left-right', 'accent' => 'text-warning'],
];

$total = count($checks);
$okCount = 0;
foreach ($checks as $check) {
    if ($check['ok']) {
        $okCount++;
    }
}
?>
<div class="health-index py-4">

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <h1 class="h3 fw-semibold m-0">
            <i class="bi bi-activity me-2 text-primary"></i><?= Html::encode($this->title) ?>
        </h1>
        <?= Html::a('<i class="bi bi-filetype-json me-1"></i>JSON', Url::to(['health/index', 'format' => 'json']), [
            'class' => 'btn btn-outline-secondary btn-sm',
            'target' => '_blank',
            'encode' => false,
        ]) ?>
    </div>

    <div class="card border-0 shadow-sm rounded-3 mb-4 <?= $healthy ? 'bg-success-subtle' : 'bg-danger-subtle' ?>">
        <div class="card-body d-flex align-items-center gap-3 p-4">
            <i class="bi <?= $healthy ? 'bi-check-circle-fill text-success' : 'bi-exclamation-octagon-fill text-danger' ?>" style="font-size: 2.5rem;"></i>
            <div>
                <div class="h4 fw-bold m-0">
                    <?= $healthy ? 'Все сервисы работают' : 'Есть недоступные сервисы' ?>
                </div>
                <div class="text-secondary">
                    Доступно <?= $okCount ?> из <?= $total ?> &middot; HTTP <?= $healthy ? '200 OK' : '503 Service Unavailable' ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <?php foreach ($checks as $check): ?>
            <?php
            $icon = $meta[$check['name']]['icon'] ?? 'bi-hdd-network';
            $accent = $meta[$check['name']]['accent'] ?? 'text-secondary';
            $latency = (int) $check['latency_ms'];
            $latencyClass = $latency < 50
                ? 'bg-success-subtle text-success-emphasis'
                : ($latency < 200 ? 'bg-warning-subtle text-warning-emphasis' : 'bg-danger-subtle text-danger-emphasis');
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm rounded-3">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="fs-3 <?= Html::encode($accent) ?>"><i class="bi <?= Html::encode($icon) ?>"></i></span>
                            <?php if ($check['ok']): ?>
                                <span class="badge rounded-pill bg-success"><i class="bi bi-check-lg me-1"></i>OK</span>
                            <?php else: ?>
                                <span class="badge rounded-pill bg-danger"><i class="bi bi-x-lg me-1"></i>FAIL</span>
                            <?php endif; ?>
                        </div>
                        <h2 class="h5 fw-semibold mb-1"><?= Html::encode($check['name']) ?></h2>
                        <div class="mb-2">
                            <span class="badge rounded-pill <?= $latencyClass ?>"><i class="bi bi-stopwatch me-1"></i><?= $latency ?> ms</span>
                        </div>
                        <p class="card-text small text-muted mb-0" style="word-break: break-word;">
                            <code><?= Html::encode($check['detail']) ?></code>
                        </p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
