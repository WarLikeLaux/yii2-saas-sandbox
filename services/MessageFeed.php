<?php

declare(strict_types=1);

namespace app\services;

use yii\db\Connection;

/**
 * Чтение чатов и ленты сообщений с keyset-пагинацией по времени.
 *
 * Сортировка и курсор — по паре `(created_at, id)`: `created_at` задаёт порядок,
 * `id` служит tiebreaker'ом (время не уникально). Список чатов опирается на
 * «loose index scan» по `(chat_id, id)`: рекурсивный CTE перебирает уникальные
 * `chat_id`, а боковое соединение (`LATERAL`) по `(chat_id, created_at, id)`
 * берёт последнее сообщение каждого чата — это исключает скан всей таблицы.
 * Поиск по тексту (`ILIKE`) выбирает стратегию по оценке селективности шаблона
 * (`EXPLAIN`): редкий шаблон отдаём триграммному индексу `pg_trgm` через
 * `DISTINCT ON` (точечный `Bitmap Index Scan`), а частый — тому же боковому
 * перебору чатов с фильтром в `LATERAL` (триграммы тут не отсекают, зато первое
 * же свежее сообщение чата обычно совпадает). Лента внутри чата опирается на тот
 * же `(chat_id, created_at, id)` и всегда упорядочена от новых к старым.
 */
class MessageFeed
{
    /**
     * @var int Порог оценки числа совпадений, выше которого поиск по тексту
     *          считается «частым»: триграммный индекс уже почти не отсекает
     *          строки, и боковой перебор чатов дешевле, чем `DISTINCT ON` по
     *          миллионам совпадений.
     */
    private const SEARCH_DENSE_THRESHOLD = 10000;

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
     * Возвращает список чатов с превью последнего сообщения.
     *
     * Список строится по последнему сообщению в каждом чате. Поддерживается
     * навигация по страницам в обоих направлениях и общий поиск по содержимому
     * сообщений внутри чатов через `LIKE`/`ILIKE`.
     *
     * @param int $limit Сколько чатов вернуть
     * @param string|null $cursorTs created_at курсора страницы
     * @param int|null $cursorId id курсора страницы
     * @param string $mode Режим: `first`, `older`, `newer`, `last`
     * @param string|null $query Общий поисковый запрос по чатам
     * @return list<array<string, mixed>> Чаты (id, chat_id, user_id, body, created_at)
     */
    public function chats(
        int $limit,
        ?string $cursorTs = null,
        ?int $cursorId = null,
        string $mode = 'first',
        ?string $query = null
    ): array {
        $params = [':limit' => $limit];

        $lateralFilter = '';
        $sparseSearch = false;
        if ($query !== null && $query !== '') {
            $like = '%' . $this->escapeLike($query) . '%';
            $params[':q'] = $like;
            if ($this->estimateMatches($like) > self::SEARCH_DENSE_THRESHOLD) {
                $lateralFilter = ' AND m.body ILIKE :q';
            } else {
                $sparseSearch = true;
            }
        }

        if ($sparseSearch) {
            $sql = 'WITH latest AS ('
                . 'SELECT DISTINCT ON (m.chat_id) m.id, m.chat_id, m.user_id, m.body, m.created_at'
                . ' FROM {{%messages}} m WHERE m.body ILIKE :q'
                . ' ORDER BY m.chat_id, m.created_at DESC, m.id DESC)';
        } else {
            $sql = 'WITH RECURSIVE ids AS ('
                . '(SELECT chat_id FROM {{%messages}} ORDER BY chat_id LIMIT 1)'
                . ' UNION ALL '
                . 'SELECT (SELECT m.chat_id FROM {{%messages}} m WHERE m.chat_id > i.chat_id'
                . ' ORDER BY m.chat_id LIMIT 1) FROM ids i WHERE i.chat_id IS NOT NULL'
                . '), latest AS ('
                . 'SELECT l.id, l.chat_id, l.user_id, l.body, l.created_at FROM ids i'
                . ' CROSS JOIN LATERAL ('
                . 'SELECT m.id, m.chat_id, m.user_id, m.body, m.created_at FROM {{%messages}} m'
                . ' WHERE m.chat_id = i.chat_id' . $lateralFilter
                . ' ORDER BY m.created_at DESC, m.id DESC LIMIT 1'
                . ') l WHERE i.chat_id IS NOT NULL)';
        }

        $reverse = false;
        switch ($mode) {
            case 'older':
                $sql .= ' SELECT id, chat_id, user_id, body, created_at FROM latest'
                    . ' WHERE (created_at, id) < (:cts, :cid)'
                    . ' ORDER BY created_at DESC, id DESC LIMIT :limit';
                $params[':cts'] = $cursorTs;
                $params[':cid'] = $cursorId;
                break;
            case 'newer':
                $sql .= ' SELECT id, chat_id, user_id, body, created_at FROM latest'
                    . ' WHERE (created_at, id) > (:cts, :cid)'
                    . ' ORDER BY created_at ASC, id ASC LIMIT :limit';
                $params[':cts'] = $cursorTs;
                $params[':cid'] = $cursorId;
                $reverse = true;
                break;
            case 'last':
                $sql .= ' SELECT id, chat_id, user_id, body, created_at FROM latest'
                    . ' ORDER BY created_at ASC, id ASC LIMIT :limit';
                $reverse = true;
                break;
            default:
                $sql .= ' SELECT id, chat_id, user_id, body, created_at FROM latest'
                    . ' ORDER BY created_at DESC, id DESC LIMIT :limit';
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->db->createCommand($sql, $params)->queryAll();

        return $reverse ? array_reverse($rows) : $rows;
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
     * Оценивает число сообщений, совпадающих с шаблоном `ILIKE`, по плану запроса.
     *
     * Берёт оценку строк верхнего узла из `EXPLAIN` (запрос не выполняется) —
     * этого хватает, чтобы по порядку величины выбрать стратегию поиска: редкий
     * шаблон отдаём триграммному индексу, частый — боковому перебору чатов.
     *
     * @param string $like Готовый шаблон для `ILIKE` (например `%текст%`)
     * @return int Оценка числа совпадающих строк (0, если оценку не удалось извлечь)
     */
    private function estimateMatches(string $like): int
    {
        /** @var list<array<string, mixed>> $plan */
        $plan = $this->db->createCommand(
            'EXPLAIN SELECT 1 FROM {{%messages}} WHERE body ILIKE :q',
            [':q' => $like]
        )->queryAll();

        foreach ($plan as $row) {
            $line = reset($row);
            if (is_string($line) && preg_match('/rows=(\d+)/', $line, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return 0;
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
