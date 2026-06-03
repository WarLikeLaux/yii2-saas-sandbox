<?php

declare(strict_types=1);

namespace app\services;

use yii\db\Connection;

/**
 * Чтение ленты сообщений чата с keyset-пагинацией (по курсору).
 *
 * Вместо OFFSET использует условие `id < :afterId`, поэтому время выборки любой
 * страницы постоянно и не растёт с её номером. Опирается на индекс
 * `(chat_id, id)` — выборка чата и упорядочивание по `id DESC` идут по индексу.
 */
class MessageFeed
{
    /**
     * @var Connection Соединение с базой данных
     */
    private $db;

    /**
     * @param Connection $db Соединение с базой данных (внедряется контейнером)
     */
    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * Возвращает одну страницу сообщений чата от новых к старым.
     *
     * @param int $chatId Идентификатор чата
     * @param int|null $afterId Курсор — id последнего сообщения предыдущей страницы; null для первой страницы
     * @param int $limit Сколько сообщений вернуть
     * @return list<array<string, mixed>> Сообщения страницы (id, user_id, body, status, created_at)
     */
    public function page(int $chatId, ?int $afterId, int $limit): array
    {
        $sql = 'SELECT id, user_id, body, status, created_at FROM {{%messages}} WHERE chat_id = :chatId';
        $params = [':chatId' => $chatId, ':limit' => $limit];

        if ($afterId !== null) {
            $sql .= ' AND id < :afterId';
            $params[':afterId'] = $afterId;
        }

        $sql .= ' ORDER BY id DESC LIMIT :limit';

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->db->createCommand($sql, $params)->queryAll();

        return $rows;
    }
}
