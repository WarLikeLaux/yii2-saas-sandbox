<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * @var yii\web\View $this Представление
 * @var app\services\MessagePageViewModel $page Модель представления страницы сообщений
 */

$this->title = 'Чаты';

$withChatsCursor = static function (array $params) use ($page): array {
    if ($page->getChatsCursorTs() !== null && $page->getChatsCursorId() !== null) {
        $params['ch_ts'] = $page->getChatsCursorTs();
        $params['ch_id'] = $page->getChatsCursorId();
    }

    return $params;
};

$nav = static function (bool $enabled, string $label, array $extra) use ($page, $withChatsCursor): string {
    if (!$enabled || $page->getChatId() === null) {
        return Html::tag('span', $label, ['class' => 'btn btn-sm btn-outline-secondary disabled']);
    }

    $params = $withChatsCursor(['/message/index', 'chat_id' => $page->getChatId()]);

    return Html::a($label, array_merge($params, $extra), ['class' => 'btn btn-sm btn-outline-primary']);
};
?>
<h1 class="h4 mb-3">Чаты</h1>

<div class="row g-3">
    <div class="col-md-4">
        <div class="list-group" style="max-height: 70vh; overflow-y: auto;">
            <?php foreach ($page->getChats() as $chat): ?>
                <?php $cid = is_numeric($chat['chat_id']) ? (int) $chat['chat_id'] : 0; ?>
                <?= Html::a(
                    '<div class="d-flex justify-content-between"><strong>Чат #' . Html::encode((string) $cid) . '</strong>'
                    . '<small class="text-muted">' . Html::encode((string) $chat['created_at']) . '</small></div>'
                    . '<div class="small text-muted text-truncate">' . Html::encode((string) $chat['body']) . '</div>',
                    $withChatsCursor(['/message/index', 'chat_id' => $cid]),
                    [
                        'class' => 'list-group-item list-group-item-action' . ($cid === $page->getChatId() ? ' active' : ''),
                        'encode' => false,
                    ]
                ) ?>
            <?php endforeach; ?>
        </div>
        <?php if ($page->getChatsNext() !== null && $page->getChatId() !== null): ?>
            <div class="d-grid mt-2">
                <?= Html::a('Ещё чаты ↓', ['/message/index', 'chat_id' => $page->getChatId(), 'ch_ts' => $page->getChatsNext()['ts'], 'ch_id' => $page->getChatsNext()['id']], ['class' => 'btn btn-sm btn-outline-secondary']) ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-md-8">
        <?php if ($page->getChatId() === null): ?>
            <div class="alert alert-secondary">Выберите чат слева.</div>
        <?php else: ?>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 m-0">Чат #<?= Html::encode((string) $page->getChatId()) ?></h2>
                <?= Html::beginForm(['/message/index'], 'get', ['class' => 'd-flex gap-2']) ?>
                    <?= Html::hiddenInput('chat_id', $page->getChatId()) ?>
                    <?php if ($page->getChatsCursorTs() !== null && $page->getChatsCursorId() !== null): ?>
                        <?= Html::hiddenInput('ch_ts', $page->getChatsCursorTs()) ?>
                        <?= Html::hiddenInput('ch_id', $page->getChatsCursorId()) ?>
                    <?php endif; ?>
                    <?= Html::textInput('q', $page->getQuery(), ['class' => 'form-control form-control-sm', 'placeholder' => 'Поиск в чате']) ?>
                    <?= Html::submitButton('Найти', ['class' => 'btn btn-sm btn-primary']) ?>
                    <?php if ($page->isSearch()): ?>
                        <?= Html::a('Сброс', $withChatsCursor(['/message/index', 'chat_id' => $page->getChatId()]), ['class' => 'btn btn-sm btn-outline-secondary']) ?>
                    <?php endif; ?>
                <?= Html::endForm() ?>
            </div>

            <?php if ($page->isSearch()): ?>
                <p class="text-secondary small">Результаты поиска «<?= Html::encode($page->getQuery()) ?>» (до <?= count($page->getMessages()) ?>):</p>
            <?php endif; ?>

            <div class="d-flex flex-column gap-2" style="max-height: 55vh; overflow-y: auto;">
                <?php foreach ($page->getMessages() as $message): ?>
                    <div class="card">
                        <div class="card-body py-2 px-3">
                            <div class="d-flex justify-content-between small text-muted">
                                <span>Пользователь #<?= Html::encode((string) $message['user_id']) ?></span>
                                <span>#<?= Html::encode((string) $message['id']) ?> &middot; <?= Html::encode((string) $message['created_at']) ?></span>
                            </div>
                            <div><?= Html::encode((string) $message['body']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if ($page->getMessages() === []): ?>
                    <div class="alert alert-secondary">Сообщений нет.</div>
                <?php endif; ?>
            </div>

            <?php if (!$page->isSearch()): ?>
                <div class="btn-group mt-3" role="group" aria-label="Навигация по сообщениям">
                    <?= $nav($page->hasNewer(), '« В начало', []) ?>
                    <?= $nav($page->hasNewer(), '‹ Назад', $page->getTopTs() !== null && $page->getTopId() !== null ? ['b_ts' => $page->getTopTs(), 'b_id' => $page->getTopId()] : []) ?>
                    <?= $nav($page->hasOlder(), 'Далее ›', $page->getBottomTs() !== null && $page->getBottomId() !== null ? ['a_ts' => $page->getBottomTs(), 'a_id' => $page->getBottomId()] : []) ?>
                    <?= $nav($page->hasOlder(), 'В конец »', ['last' => 1]) ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
