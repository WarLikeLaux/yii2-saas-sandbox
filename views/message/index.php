<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\Pjax;

/**
 * @var yii\web\View $this Представление
 * @var app\services\MessagePageViewModel $page Модель представления страницы сообщений
 */

$this->title = 'Чаты';

$withChatQuery = static function (array $params) use ($page): array {
    if ($page->getChatQuery() !== '') {
        $params['chat_q'] = $page->getChatQuery();
    }

    return $params;
};

$withChatCursor = static function (array $params) use ($page, $withChatQuery): array {
    $params = $withChatQuery($params);

    if ($page->getChatsCursorTs() !== null && $page->getChatsCursorId() !== null) {
        $params['ch_ts'] = $page->getChatsCursorTs();
        $params['ch_id'] = $page->getChatsCursorId();
    }

    return $params;
};

$nav = static function (bool $enabled, string $label, array $params) use ($page, $withChatQuery): string {
    if (!$enabled || $page->getChatId() === null) {
        return Html::tag('span', $label, ['class' => 'btn btn-sm btn-outline-secondary disabled']);
    }

    $params['chat_id'] = $page->getChatId();

    return Html::a($label, $withChatQuery($params), ['class' => 'btn btn-sm btn-outline-primary']);
};
?>
<h1 class="h4 mb-3">Чаты</h1>

<?php Pjax::begin(['id' => 'chat-pjax', 'timeout' => 8000]) ?>
<div class="row g-3">
    <div class="col-md-4">
        <?= Html::beginForm(['/message/index'], 'get', ['class' => 'd-flex gap-2 mb-2', 'data-pjax' => '']) ?>
            <?= Html::textInput('chat_q', $page->getChatQuery(), ['class' => 'form-control form-control-sm', 'placeholder' => 'Поиск по чатам']) ?>
            <?php if ($page->getChatId() !== null): ?>
                <?= Html::hiddenInput('chat_id', $page->getChatId()) ?>
            <?php endif; ?>
            <?= Html::submitButton('Найти', ['class' => 'btn btn-sm btn-primary']) ?>
        <?= Html::endForm() ?>

        <div class="list-group" style="max-height: 70vh; overflow-y: auto;">
            <?php foreach ($page->getChats() as $chat): ?>
                <?php $cid = is_numeric($chat['chat_id']) ? (int) $chat['chat_id'] : 0; ?>
                <?= Html::a(
                    '<div class="d-flex justify-content-between"><strong>Чат #' . Html::encode((string) $cid) . '</strong>'
                    . '<small class="text-muted">' . Html::encode((string) $chat['created_at']) . '</small></div>'
                    . '<div class="small text-muted text-truncate">' . Html::encode((string) $chat['body']) . '</div>',
                    $withChatCursor(['/message/index', 'chat_id' => $cid]),
                    [
                        'class' => 'list-group-item list-group-item-action' . ($cid === $page->getChatId() ? ' active' : ''),
                        'encode' => false,
                    ]
                ) ?>
            <?php endforeach; ?>
        </div>
        <div class="btn-group mt-2" role="group" aria-label="Навигация по чатам">
            <?= $nav($page->hasChatsNewer(), '« В начало', ['/message/index']) ?>
            <?= $nav($page->hasChatsNewer(), '‹ Назад', ['/message/index', 'ch_dir' => 'newer', 'ch_ts' => $page->getChatsTopTs(), 'ch_id' => $page->getChatsTopId()]) ?>
            <?= $nav($page->hasChatsOlder(), 'Далее ›', ['/message/index', 'ch_dir' => 'older', 'ch_ts' => $page->getChatsBottomTs(), 'ch_id' => $page->getChatsBottomId()]) ?>
            <?= $nav($page->hasChatsOlder(), 'В конец »', ['/message/index', 'ch_dir' => 'last']) ?>
        </div>
    </div>

    <div class="col-md-8">
        <?php if ($page->getChatId() === null): ?>
            <div class="alert alert-secondary">Выберите чат слева.</div>
        <?php else: ?>
            <?= Html::beginForm(['/message/index'], 'get', ['class' => 'd-flex gap-2 mb-2', 'data-pjax' => '']) ?>
                <?= Html::hiddenInput('chat_id', $page->getChatId()) ?>
                <?php if ($page->getChatsCursorTs() !== null && $page->getChatsCursorId() !== null): ?>
                    <?= Html::hiddenInput('ch_ts', $page->getChatsCursorTs()) ?>
                    <?= Html::hiddenInput('ch_id', $page->getChatsCursorId()) ?>
                <?php endif; ?>
                <?= Html::textInput('q', $page->getQuery(), ['class' => 'form-control form-control-sm', 'placeholder' => 'Поиск в чате']) ?>
                <?= Html::submitButton('Найти', ['class' => 'btn btn-sm btn-primary']) ?>
                <?php if ($page->isSearch()): ?>
                    <?= Html::a('Сброс', $withChatCursor(['/message/index', 'chat_id' => $page->getChatId()]), ['class' => 'btn btn-sm btn-outline-secondary']) ?>
                <?php endif; ?>
            <?= Html::endForm() ?>

            <?php if ($page->isSearch()): ?>
                <p class="text-secondary small">Результаты поиска «<?= Html::encode($page->getQuery()) ?>» (до <?= count($page->getMessages()) ?>):</p>
            <?php endif; ?>

            <div class="d-flex flex-column gap-2" style="max-height: 70vh; overflow-y: auto;">
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
<?php Pjax::end() ?>
