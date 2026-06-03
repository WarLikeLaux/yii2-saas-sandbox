<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\MessageFeed;
use yii\base\Module;
use yii\web\Controller;

/**
 * Лента сообщений чата с keyset-пагинацией.
 *
 * GET /messages?chat_id=427           — первая страница (новые сверху)
 * GET /messages?chat_id=427&after=ID  — следующая страница (курсор — id последней строки)
 */
class MessageController extends Controller
{
    /**
     * @var int Размер страницы
     */
    private const PAGE_SIZE = 20;

    /**
     * @var MessageFeed Сервис чтения ленты сообщений
     */
    private $feed;

    /**
     * @param string $id Идентификатор контроллера
     * @param Module $module Модуль, которому принадлежит контроллер
     * @param MessageFeed $feed Сервис чтения ленты (внедряется контейнером)
     * @param array<string, mixed> $config Дополнительная конфигурация
     */
    public function __construct(string $id, Module $module, MessageFeed $feed, array $config = [])
    {
        $this->feed = $feed;
        parent::__construct($id, $module, $config);
    }

    /**
     * Отдаёт страницу сообщений чата и курсор на следующую.
     *
     * @return string HTML-разметка страницы
     */
    public function actionIndex(): string
    {
        $chatIdRaw = $this->request->get('chat_id', '1');
        $chatId = is_numeric($chatIdRaw) ? (int) $chatIdRaw : 1;

        $afterRaw = $this->request->get('after');
        $afterId = is_numeric($afterRaw) ? (int) $afterRaw : null;

        $messages = $this->feed->page($chatId, $afterId, self::PAGE_SIZE);

        $nextCursor = null;
        if (count($messages) === self::PAGE_SIZE) {
            $lastId = $messages[count($messages) - 1]['id'];
            $nextCursor = is_numeric($lastId) ? (int) $lastId : null;
        }

        return $this->render('index', [
            'chatId' => $chatId,
            'messages' => $messages,
            'nextCursor' => $nextCursor,
        ]);
    }
}
