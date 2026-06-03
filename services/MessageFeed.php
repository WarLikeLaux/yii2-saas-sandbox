<?php

declare(strict_types=1);

namespace app\services;

use yii\db\Connection;

/**
 * Чтение чатов и ленты сообщений с keyset-пагинацией по времени.
 *
 * Сортировка и курсор — по паре `(created_at, id)`: `created_at` задаёт порядок,
 * `id` служит tiebreaker'ом (время не уникально). Опора — индексы
 * `(created_at, id)` для глобального списка чатов и `(chat_id, created_at)` для
 * ленты внутри чата. Лента всегда упорядочена от новых к старым.
 */
class MessageFeed
{
    /**
     * @var string Значение `created_at`-курсора, означающее «с самого начала» (свежее любого)
     */
    private const TOP_TS = '9999-12-31 23:59:59';

    /**
     * @var int Значение id-курсора, означающее «с самого начала»
     */
    private const TOP_ID = PHP_INT_MAX;

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
     * Возвращает список чатов с превью последнего (по времени) сообщения.
     *
     * Loose index scan через рекурсивный CTE по `(created_at, id)`: шагает по
     * убыванию времени, на каждом шаге берёт последнее сообщение ещё не
     * показанного чата — до `limit` чатов. Для пагинации курсор `(cursorTs,
     * cursorId)` отсекает уже показанные чаты (у которых есть сообщение свежее
     * либо равное курсору), `null`-курсор означает первую страницу.
     *
     * @param int $limit Сколько чатов вернуть
     * @param string|null $cursorTs created_at последнего чата предыдущей страницы
     * @param int|null $cursorId id последнего чата предыдущей страницы
     * @return list<array<string, mixed>> Чаты (id, chat_id, user_id, body, created_at)
     */
    public function chats(int $limit, ?string $cursorTs = null, ?int $cursorId = null): array
    {
        $cts = $cursorTs ?? self::TOP_TS;
        $cid = $cursorId ?? self::TOP_ID;

        $notShown = static function (string $alias): string {
            return 'NOT EXISTS (SELECT 1 FROM {{%messages}} e WHERE e.chat_id = ' . $alias . '.chat_id'
                . ' AND (e.created_at, e.id) >= (:cts, :cid))';
        };

        $sql = 'WITH RECURSIVE t AS ('
            . '(SELECT id, chat_id, user_id, body, created_at, ARRAY[chat_id] AS seen FROM {{%messages}} m'
            . ' WHERE (m.created_at, m.id) < (:cts, :cid) AND ' . $notShown('m')
            . ' ORDER BY m.created_at DESC, m.id DESC LIMIT 1)'
            . ' UNION ALL'
            . ' SELECT n.id, n.chat_id, n.user_id, n.body, n.created_at, t.seen || n.chat_id'
            . ' FROM t CROSS JOIN LATERAL ('
            . 'SELECT id, chat_id, user_id, body, created_at FROM {{%messages}} n'
            . ' WHERE (n.created_at, n.id) < (t.created_at, t.id) AND NOT (n.chat_id = ANY(t.seen))'
            . ' AND ' . $notShown('n')
            . ' ORDER BY n.created_at DESC, n.id DESC LIMIT 1'
            . ') n WHERE array_length(t.seen, 1) < :limit'
            . ') SELECT id, chat_id, user_id, body, created_at FROM t';

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->db->createCommand($sql, [':cts' => $cts, ':cid' => $cid, ':limit' => $limit])->queryAll();

        return $rows;
    }

    /**
     * Ищет сообщения чата по подстроке текста (поиск внутри чата), новые сверху.
     *
     * @param int $chatId Идентификатор чата
     * @param string $query Искомая подстрока
     * @param int $limit Сколько сообщений вернуть
     * @return list<array<string, mixed>> Найденные сообщения (id, user_id, body, status, created_at)
     */
    public function search(int $chatId, string $query, int $limit): array
    {
        $sql = 'SELECT id, user_id, body, status, created_at FROM {{%messages}}'
            . ' WHERE chat_id = :chatId AND body ILIKE :q ORDER BY created_at DESC, id DESC LIMIT :limit';

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->db->createCommand($sql, [
            ':chatId' => $chatId,
            ':q' => '%' . $this->escapeLike($query) . '%',
            ':limit' => $limit,
        ])->queryAll();

        return $rows;
    }

    /**
     * Возвращает одну страницу сообщений чата в порядке от новых к старым.
     *
     * @param int $chatId Идентификатор чата
     * @param string $mode Режим: `first` (новейшие), `last` (старейшие), `older` (старее курсора), `newer` (новее курсора)
     * @param string|null $cursorTs created_at курсора для режимов `older`/`newer`
     * @param int|null $cursorId id курсора для режимов `older`/`newer`
     * @param int $limit Сколько сообщений вернуть
     * @return list<array<string, mixed>> Сообщения страницы (id, user_id, body, status, created_at)
     */
    public function page(int $chatId, string $mode, ?string $cursorTs, ?int $cursorId, int $limit): array
    {
        $base = 'SELECT id, user_id, body, status, created_at FROM {{%messages}} WHERE chat_id = :chatId';
        $params = [':chatId' => $chatId, ':limit' => $limit];

        switch ($mode) {
            case 'older':
                $sql = $base . ' AND (created_at, id) < (:cts, :cid) ORDER BY created_at DESC, id DESC LIMIT :limit';
                $params[':cts'] = $cursorTs;
                $params[':cid'] = $cursorId;
                $reverse = false;
                break;
            case 'newer':
                $sql = $base . ' AND (created_at, id) > (:cts, :cid) ORDER BY created_at ASC, id ASC LIMIT :limit';
                $params[':cts'] = $cursorTs;
                $params[':cid'] = $cursorId;
                $reverse = true;
                break;
            case 'last':
                $sql = $base . ' ORDER BY created_at ASC, id ASC LIMIT :limit';
                $reverse = true;
                break;
            default:
                $sql = $base . ' ORDER BY created_at DESC, id DESC LIMIT :limit';
                $reverse = false;
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->db->createCommand($sql, $params)->queryAll();

        return $reverse ? array_reverse($rows) : $rows;
    }

    /**
     * Есть ли в чате сообщения новее курсора (доступна ли навигация назад).
     *
     * @param int $chatId Идентификатор чата
     * @param string $ts created_at курсора
     * @param int $id id курсора
     * @return bool true, если существует более новое сообщение
     */
    public function hasNewer(int $chatId, string $ts, int $id): bool
    {
        return $this->exists($chatId, '(created_at, id) > (:cts, :cid)', $ts, $id);
    }

    /**
     * Есть ли в чате сообщения старее курсора (доступна ли навигация вперёд).
     *
     * @param int $chatId Идентификатор чата
     * @param string $ts created_at курсора
     * @param int $id id курсора
     * @return bool true, если существует более старое сообщение
     */
    public function hasOlder(int $chatId, string $ts, int $id): bool
    {
        return $this->exists($chatId, '(created_at, id) < (:cts, :cid)', $ts, $id);
    }

    /**
     * Проверяет существование сообщения чата по условию относительно курсора.
     *
     * @param int $chatId Идентификатор чата
     * @param string $condition Условие сравнения по `(created_at, id)`
     * @param string $ts created_at курсора
     * @param int $id id курсора
     * @return bool true, если такое сообщение существует
     */
    private function exists(int $chatId, string $condition, string $ts, int $id): bool
    {
        $scalar = $this->db->createCommand(
            'SELECT EXISTS(SELECT 1 FROM {{%messages}} WHERE chat_id = :chatId AND ' . $condition . ')::int',
            [':chatId' => $chatId, ':cts' => $ts, ':cid' => $id]
        )->queryScalar();

        return is_numeric($scalar) && (int) $scalar === 1;
    }

    /**
     * Экранирует спецсимволы LIKE (`%`, `_`, `\`) в пользовательском вводе.
     *
     * @param string $value Исходная подстрока
     * @return string Подстрока, безопасная для подстановки в шаблон LIKE
     */
    private function escapeLike(string $value): string
    {
        return strtr($value, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']);
    }
}
