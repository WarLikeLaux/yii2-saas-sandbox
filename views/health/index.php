<?php

/** @var yii\web\View $this */
/** @var bool $healthy */
/** @var array[] $checks */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Health';
?>
<div class="health-index">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h1 class="m-0">Состояние сервисов</h1>
        <?= Html::a('JSON', Url::to(['health/index', 'format' => 'json']), [
            'class' => 'btn btn-outline-secondary btn-sm',
            'target' => '_blank',
        ]) ?>
    </div>

    <?php if ($healthy): ?>
        <div class="alert alert-success" role="alert">
            <strong>OK</strong> — все сервисы доступны.
        </div>
    <?php else: ?>
        <div class="alert alert-danger" role="alert">
            <strong>DEGRADED</strong> — есть недоступные сервисы (HTTP 503).
        </div>
    <?php endif; ?>

    <table class="table table-bordered align-middle">
        <thead class="table-light">
            <tr>
                <th style="width: 18%">Сервис</th>
                <th style="width: 12%">Статус</th>
                <th>Детали</th>
                <th style="width: 12%" class="text-end">Задержка</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($checks as $check): ?>
            <tr>
                <td><?= Html::encode($check['name']) ?></td>
                <td>
                    <?php if ($check['ok']): ?>
                        <span class="badge bg-success">OK</span>
                    <?php else: ?>
                        <span class="badge bg-danger">FAIL</span>
                    <?php endif; ?>
                </td>
                <td><code><?= Html::encode($check['detail']) ?></code></td>
                <td class="text-end text-muted"><?= (int) $check['latency_ms'] ?> ms</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
