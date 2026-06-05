<?php

/**
 * @var yii\web\View $this
 */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'SaaS Sandbox';

$stackBadges = ['PHP 7.4', 'Yii2', 'PostgreSQL 16', 'PgBouncer', 'Redis 7', 'RabbitMQ 3', 'Nginx'];

$links = [
    ['icon' => 'bi-chat-dots', 'title' => 'Лента сообщений', 'text' => 'Чаты и сообщения: keyset-пагинация, поиск, денормализация.', 'url' => Url::to(['/message/index']), 'accent' => 'primary', 'external' => false],
    ['icon' => 'bi-activity', 'title' => 'Состояние сервисов', 'text' => 'Health-check: PostgreSQL, Redis, RabbitMQ.', 'url' => Url::to(['/health']), 'accent' => 'success', 'external' => false],
    ['icon' => 'bi-graph-up', 'title' => 'Kibana — логи', 'text' => 'Поиск по JSON-логам (профиль observability).', 'url' => 'http://localhost:5601', 'accent' => 'info', 'external' => true],
    ['icon' => 'bi-arrow-left-right', 'title' => 'RabbitMQ', 'text' => 'Очереди и воркеры (guest / guest).', 'url' => 'http://localhost:15672', 'accent' => 'warning', 'external' => true],
    ['icon' => 'bi-database', 'title' => 'Adminer — БД', 'text' => 'Просмотр PostgreSQL напрямую.', 'url' => 'http://localhost:8080', 'accent' => 'secondary', 'external' => true],
];

$demos = [
    ['key' => 'cache', 'icon' => 'bi-database-check', 'title' => 'Кеш + анти-stampede', 'text' => 'COUNT по 1 млн через кеш: промах против попадания.'],
    ['key' => 'rate', 'icon' => 'bi-speedometer2', 'title' => 'Rate limiting', 'text' => 'Token bucket на Redis: 10 запросов при ведре 5.'],
    ['key' => 'breaker', 'icon' => 'bi-shield-exclamation', 'title' => 'Circuit breaker', 'text' => 'Серия отказов размыкает цепь — быстрый отказ.'],
    ['key' => 'mutex', 'icon' => 'bi-lock', 'title' => 'Mutex / single-flight', 'text' => 'Второй заход за ту же блокировку пропускается.'],
    ['key' => 'outbox', 'icon' => 'bi-box-seam', 'title' => 'Transactional outbox', 'text' => 'Событие в транзакции → relay перекладывает в очередь.'],
    ['key' => 'rls', 'icon' => 'bi-people', 'title' => 'Multi-tenancy (RLS)', 'text' => 'Под ролью тенанта видны только свои строки.'],
    ['key' => 'log', 'icon' => 'bi-journal-text', 'title' => 'Структурный лог', 'text' => 'JSON-лог с correlation id (видно в Kibana).'],
    ['key' => 'sentry', 'icon' => 'bi-bug', 'title' => 'Sentry', 'text' => 'Отлов ошибок (no-op без SENTRY_DSN).'],
    ['key' => 'queue', 'icon' => 'bi-send', 'title' => 'Очередь (RabbitMQ)', 'text' => 'Поставить фоновую задачу в очередь.'],
];

$play = Yii::$app->session->getFlash('play');
?>
<div class="site-index">

    <section class="text-center py-5 my-2">
        <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis mb-3 px-3 py-2">
            <i class="bi bi-stars me-1"></i>High-load бэкенд-песочница
        </span>
        <h1 class="display-4 fw-bold mb-3"><?= Html::encode($this->title) ?></h1>
        <p class="lead text-secondary mb-3">Стенд приёмов высоких нагрузок и отказоустойчивости — потрогать прямо в браузере</p>
        <div class="d-flex flex-wrap justify-content-center gap-2">
            <?php foreach ($stackBadges as $badge): ?>
                <span class="badge rounded-pill bg-dark px-3 py-2 fw-normal"><?= Html::encode($badge) ?></span>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="py-3">
        <h2 class="h4 fw-semibold mb-4"><i class="bi bi-grid-3x3-gap me-2 text-primary"></i>Сервисы стенда</h2>
        <div class="row g-4">
            <?php foreach ($links as $link): ?>
                <div class="col-sm-6 col-lg-4">
                    <?= Html::a(
                        '<div class="card h-100 border-0 shadow-sm rounded-3 link-card">'
                        . '<div class="card-body p-4">'
                        . '<div class="fs-2 mb-2 text-' . Html::encode($link['accent']) . '">'
                        . '<i class="bi ' . Html::encode($link['icon']) . '"></i>'
                        . ($link['external'] ? '<i class="bi bi-box-arrow-up-right fs-6 ms-2 text-muted"></i>' : '')
                        . '</div>'
                        . '<h3 class="h5 fw-semibold mb-1">' . Html::encode($link['title']) . '</h3>'
                        . '<p class="card-text text-secondary mb-0">' . Html::encode($link['text']) . '</p>'
                        . '</div></div>',
                        $link['url'],
                        array_merge(
                            ['class' => 'text-decoration-none text-reset'],
                            $link['external'] ? ['target' => '_blank', 'rel' => 'noopener'] : []
                        )
                    ) ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="py-4" id="playground">
        <h2 class="h4 fw-semibold mb-2"><i class="bi bi-joystick me-2 text-primary"></i>Песочница: запусти приём</h2>
        <p class="text-secondary mb-4">Каждая кнопка выполняет приём на живых сервисах и показывает результат.</p>

        <?php if (is_array($play)): ?>
            <?php
            $ok = !empty($play['ok']);
            $title = is_string($play['title'] ?? null) ? $play['title'] : 'Результат';
            $output = is_string($play['output'] ?? null) ? $play['output'] : '';
            ?>
            <div class="card border-0 shadow-sm rounded-3 mb-4 border-start border-4 border-<?= $ok ? 'success' : 'danger' ?>">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-2">
                        <span class="fs-4 me-2 text-<?= $ok ? 'success' : 'danger' ?>">
                            <i class="bi <?= $ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"></i>
                        </span>
                        <h3 class="h5 fw-semibold m-0"><?= Html::encode($title) ?></h3>
                    </div>
                    <pre class="bg-dark text-light rounded-3 p-3 mb-0" style="white-space: pre-wrap;"><?= Html::encode($output) ?></pre>
                </div>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <?php foreach ($demos as $demo): ?>
                <div class="col-sm-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm rounded-3">
                        <div class="card-body p-4 d-flex flex-column">
                            <div class="fs-2 mb-2 text-primary"><i class="bi <?= Html::encode($demo['icon']) ?>"></i></div>
                            <h3 class="h5 fw-semibold mb-1"><?= Html::encode($demo['title']) ?></h3>
                            <p class="card-text text-secondary flex-grow-1"><?= Html::encode($demo['text']) ?></p>
                            <?= Html::beginForm(['site/play'], 'post') ?>
                                <?= Html::hiddenInput('demo', $demo['key']) ?>
                                <?= Html::submitButton(
                                    '<i class="bi bi-play-fill me-1"></i>Запустить',
                                    ['class' => 'btn btn-outline-primary w-100', 'encode' => false]
                                ) ?>
                            <?= Html::endForm() ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="py-4 mb-2">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-body p-4">
                <h2 class="h5 fw-semibold mb-3"><i class="bi bi-terminal me-2 text-success"></i>Тулчейн качества</h2>
                <p class="text-secondary mb-2">Строгий гейт без baseline — код проходит начисто:</p>
                <div class="d-flex flex-wrap gap-2">
                    <code class="badge bg-light text-dark border fw-normal p-2">make dev</code>
                    <code class="badge bg-light text-dark border fw-normal p-2">make analyze — PHPStan level 9</code>
                    <code class="badge bg-light text-dark border fw-normal p-2">make test</code>
                    <code class="badge bg-light text-dark border fw-normal p-2">make ci</code>
                </div>
            </div>
        </div>
    </section>

</div>
