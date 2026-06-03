<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this Представление */
/** @var int $chatId Идентификатор чата */
/** @var list<array<string, mixed>> $messages Сообщения текущей страницы */
/** @var int|null $nextCursor Курсор следующей страницы или null, если это конец */

$this->title = "Сообщения чата #{$chatId}";
?>
<h1><?= Html::encode($this->title) ?></h1>

<p>Keyset-пагинация: следующая страница берётся по <code>id &lt; курсор</code>, без OFFSET.</p>

<table class="table table-striped table-bordered">
    <thead>
        <tr>
            <th>ID</th>
            <th>Пользователь</th>
            <th>Текст</th>
            <th>Статус</th>
            <th>Создано</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($messages as $message): ?>
        <tr>
            <td><?= Html::encode((string) $message['id']) ?></td>
            <td><?= Html::encode((string) $message['user_id']) ?></td>
            <td><?= Html::encode((string) $message['body']) ?></td>
            <td><?= Html::encode((string) $message['status']) ?></td>
            <td><?= Html::encode((string) $message['created_at']) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if ($messages === []): ?>
        <tr><td colspan="5">Сообщений нет.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<?php if ($nextCursor !== null): ?>
    <?= Html::a('Дальше →', Url::to(['message/index', 'chat_id' => $chatId, 'after' => $nextCursor]), ['class' => 'btn btn-primary']) ?>
<?php else: ?>
    <p>Это все сообщения чата.</p>
<?php endif; ?>
