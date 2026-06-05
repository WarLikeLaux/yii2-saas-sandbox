<?php

/**
 * @var yii\web\View $this
 * @var string $content
 */

use app\assets\AppAsset;
use app\widgets\Alert;
use yii\bootstrap5\Breadcrumbs;
use yii\bootstrap5\Html;
use yii\bootstrap5\NavBar;

AppAsset::register($this);

$this->registerJs(<<<'JS'
fetch('/health?format=json', {headers: {'Accept': 'application/json'}})
    .then(function (response) { return response.json(); })
    .then(function (data) {
        var el = document.getElementById('health-status');
        if (!el) { return; }
        var ok = data.status === 'ok';
        el.className = 'badge ' + (ok ? 'bg-success' : 'bg-danger');
        el.innerHTML = '<i class="bi bi-' + (ok ? 'check-circle' : 'exclamation-triangle') + ' me-1"></i>Сервисы: ' + (ok ? 'OK' : 'проблемы');
        if (Array.isArray(data.checks) && el.parentElement) {
            el.parentElement.title = data.checks.map(function (c) {
                return c.name + ': ' + (c.ok ? 'ok' : 'сбой') + ' (' + c.latency_ms + 'ms)';
            }).join('\n');
        }
    })
    .catch(function () {
        var el = document.getElementById('health-status');
        if (el) {
            el.className = 'badge bg-warning text-dark';
            el.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>Сервисы: недоступно';
        }
    });
JS);

$this->registerCsrfMetaTags();
$this->registerMetaTag(['charset' => Yii::$app->charset], 'charset');
$this->registerMetaTag(['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1, shrink-to-fit=no']);
$this->registerMetaTag(['name' => 'description', 'content' => $this->params['meta_description'] ?? '']);
$this->registerMetaTag(['name' => 'keywords', 'content' => $this->params['meta_keywords'] ?? '']);
$this->registerLinkTag(['rel' => 'icon', 'type' => 'image/x-icon', 'href' => Yii::getAlias('@web/favicon.ico')]);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" class="h-100">
<head>
    <title><?= Html::encode($this->title) ?></title>
    <?php $this->head() ?>
</head>
<body class="d-flex flex-column h-100">
<?php $this->beginBody() ?>

<header id="header">
    <?php
    NavBar::begin([
        'brandLabel' => '<i class="bi bi-box-seam me-2"></i>' . Html::encode(Yii::$app->name),
        'brandUrl' => Yii::$app->homeUrl,
        'options' => ['class' => 'navbar-dark bg-dark fixed-top shadow-sm'],
    ]);
    NavBar::end();
    ?>
</header>

<main id="main" class="flex-shrink-0" role="main">
    <div class="container">
        <?php if (!empty($this->params['breadcrumbs'])): ?>
            <?= Breadcrumbs::widget(['links' => $this->params['breadcrumbs']]) ?>
        <?php endif ?>
        <?= Alert::widget() ?>
        <?= $content ?>
    </div>
</main>

<footer id="footer" class="mt-auto py-3 bg-dark text-light">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start small">
                &copy; <?= Html::encode(Yii::$app->name) ?> <?= date('Y') ?>
            </div>
            <div class="col-md-6 text-center text-md-end small text-secondary">
                <?= Html::a(
                    '<span id="health-status" class="badge bg-secondary"><i class="bi bi-hourglass-split me-1"></i>Сервисы: проверка…</span>',
                    ['/health'],
                    ['class' => 'text-decoration-none me-3', 'encode' => false, 'title' => 'Подробнее о состоянии']
                ) ?>
                PHP 7.4 &middot; Yii2 Basic &middot; Docker
            </div>
        </div>
    </div>
</footer>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
