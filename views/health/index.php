<?php

/** @var yii\web\View $this */
/** @var bool $healthy */
/** @var array[] $checks */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Health';
?>
<div class="health-index py-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <h1 class="h3 fw-semibold m-0">
            <i class="bi bi-activity me-2 text-primary"></i>Состояние сервисов
        </h1>
        <?= Html::a('<i class="bi bi-filetype-json me-1"></i>JSON', Url::to(['health/index', 'format' => 'json']), [
            'class' => 'btn btn-outline-secondary btn-sm',
            'target' => '_blank',
            'encode' => false,
        ]) ?>
    </div>

    <?php if ($healthy): ?>
        <div class="alert alert-success d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <span><strong>OK</strong> — все сервисы доступны.</span>
        </div>
    <?php else: ?>
        <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-exclamation-octagon-fill fs-5"></i>
            <span><strong>DEGRADED</strong> — есть недоступные сервисы (HTTP 503).</span>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle m-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width: 22%">Сервис</th>
                        <th style="width: 12%">Статус</th>
                        <th>Детали</th>
                        <th class="text-end pe-3" style="width: 14%">Задержка</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($checks as $check): ?>
                    <tr>
                        <td class="ps-3 fw-medium"><?= Html::encode($check['name']) ?></td>
                        <td>
                            <?php if ($check['ok']): ?>
                                <span class="badge rounded-pill bg-success">
                                    <i class="bi bi-check-lg me-1"></i>OK
                                </span>
                            <?php else: ?>
                                <span class="badge rounded-pill bg-danger">
                                    <i class="bi bi-x-lg me-1"></i>FAIL
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><code><?= Html::encode($check['detail']) ?></code></td>
                        <td class="text-end pe-3 text-muted"><?= (int) $check['latency_ms'] ?> ms</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
