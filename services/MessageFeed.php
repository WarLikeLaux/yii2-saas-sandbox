<?php

declare(strict_types=1);

namespace app\services;

use app\helpers\DbHelper;
use yii\db\Connection;

/**
 * Чтение списка чатов и ленты сообщений.
 *
 * Пагинация — keyset (по курсору), а не через `OFFSET`: следующая страница
 * берётся условием на ключ `(created_at, id)`, поэтому время выборки не растёт
 * с её глубиной. `created_at` задаёт порядок, `id` разрывает совпадения по
 * времени (tiebreaker, т.к. время не уникально).
 *
 * Список чатов собирается одним из трёх способов — в зависимости от запроса:
 *  - без поиска — из денормализованной таблицы `chats` (готовый снимок последнего
 *    сообщения каждого чата, его обновляет триггер на `messages`); запрос сводится
 *    к `ORDER BY last_message_at DESC LIMIT N` по индексу, и стоимость не зависит
 *    ни от числа чатов, ни от числа сообщений;
 *  - поиск с редким шаблоном — по триграммному индексу `pg_trgm`: `DISTINCT ON`
 *    берёт последнее совпадение на чат, а тяжёлый `body` подтягивается `JOIN`'ом
 *    по первичному ключу (иначе сортировка десятков тысяч строк с телами
 *    переполнит `work_mem` и сбросится во временные файлы на диск);
 *  - поиск с частым шаблоном — перебором чатов с фильтром в `LATERAL` (триграммы
 *    тут почти не отсекают, зато первое же свежее сообщение чата обычно подходит).
 * Какой из двух способов поиска выбрать, решает {@see estimateMatches()} по
 * оценке числа совпадений из плана запроса.
 *
 * Лента внутри чата ({@see page()}) опирается на индекс `(chat_id, created_at, id)`
 * и всегда упорядочена от новых к старым.
 */
class MessageFeed
{
    /**
     * @var int Порог оценки числа совпадений, выше которого поиск по тексту
     *          считается «частым»: триграммный индекс уже почти не отсекает
     *          строки, и боковой перебор чатов дешевле, чем `DISTINCT ON` по
     *          миллионам совпадений.
     */
    private const SEARCH_DENSE_THRESHOLD = 25000;

    /**
     * @var Connection Соединение с базой данных
     */
    private $db;

    /**
     * @var DbHelper Помощник для подготовки значений к SQL (экранирование `LIKE`)
     */
    private $dbHelper;

    /**
     * @param Connection $db Соединение с базой данных (внедряется контейнером)
     * @param DbHelper $dbHelper Помощник для подготовки значений к SQL (экранирование `LIKE`)
     */
    public function __construct(Connection $db, DbHelper $dbHelper)
    {
        $this->db = $db;
        $this->dbHelper = $dbHelper;
    }

    /**
     * Возвращает список чатов с превью последнего сообщения, свежие — сверху.
     *
     * Без поискового запроса читает готовый снимок из таблицы `chats`. При поиске
     * фильтрует сообщения по тексту и выбирает одну из двух стратегий по оценке
     * селективности (детали — в описании класса): редкий шаблон — через
     * триграммный индекс, частый — перебором чатов. Навигация keyset в обе стороны.
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

        $search = '';
        if ($query !== null && $query !== '') {
            $like = '%' . $this->dbHelper->escapeLike($query) . '%';
            $params[':q'] = $like;
            $search = $this->estimateMatches($like) > self::SEARCH_DENSE_THRESHOLD ? 'dense' : 'sparse';
        }

        if ($search === 'sparse') {
            $sql = 'WITH keys AS ('
                . 'SELECT DISTINCT ON (m.chat_id) m.id, m.chat_id, m.created_at'
                . ' FROM {{%messages}} m WHERE m.body ILIKE :q'
                . ' ORDER BY m.chat_id, m.created_at DESC, m.id DESC'
                . '), latest AS ('
                . 'SELECT k.id, k.chat_id, mm.user_id, mm.body, k.created_at'
                . ' FROM keys k JOIN {{%messages}} mm ON mm.id = k.id)';
        } elseif ($search === 'dense') {
            $sql = 'WITH RECURSIVE ids AS ('
                . '(SELECT chat_id FROM {{%messages}} ORDER BY chat_id LIMIT 1)'
                . ' UNION ALL '
                . 'SELECT (SELECT m.chat_id FROM {{%messages}} m WHERE m.chat_id > i.chat_id'
                . ' ORDER BY m.chat_id LIMIT 1) FROM ids i WHERE i.chat_id IS NOT NULL'
                . '), latest AS ('
                . 'SELECT l.id, l.chat_id, l.user_id, l.body, l.created_at FROM ids i'
                . ' CROSS JOIN LATERAL ('
                . 'SELECT m.id, m.chat_id, m.user_id, m.body, m.created_at FROM {{%messages}} m'
                . ' WHERE m.chat_id = i.chat_id AND m.body ILIKE :q'
                . ' ORDER BY m.created_at DESC, m.id DESC LIMIT 1'
                . ') l WHERE i.chat_id IS NOT NULL)';
        } else {
            $sql = 'WITH latest AS ('
                . 'SELECT last_message_id AS id, id AS chat_id, last_user_id AS user_id,'
                . ' last_body AS body, last_message_at AS created_at FROM {{%chats}})';
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
            ':q' => '%' . $this->dbHelper->escapeLike($query) . '%',
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
     * Берёт оценку строк верхнего узла из `EXPLAIN (FORMAT JSON)` (запрос не
     * выполняется) — этого хватает, чтобы по порядку величины выбрать стратегию
     * поиска: редкий шаблон отдаём триграммному индексу, частый — боковому
     * перебору чатов. JSON-формат разбирается структурно (`Plan.Plan Rows`), без
     * парсинга текста, поэтому устойчив к смене формата вывода между версиями СУБД.
     *
     * @param string $like Готовый шаблон для `ILIKE` (например `%текст%`)
     * @return int Оценка числа совпадающих строк (0, если оценку не удалось извлечь)
     */
    private function estimateMatches(string $like): int
    {
        $json = $this->db->createCommand(
            'EXPLAIN (FORMAT JSON) SELECT 1 FROM {{%messages}} WHERE body ILIKE :q',
            [':q' => $like]
        )->queryScalar();

        if (!is_string($json)) {
            return 0;
        }

        $plan = json_decode($json, true);
        if (!is_array($plan) || !isset($plan[0]) || !is_array($plan[0])) {
            return 0;
        }

        $node = $plan[0]['Plan'] ?? null;
        if (!is_array($node) || !isset($node['Plan Rows']) || !is_numeric($node['Plan Rows'])) {
            return 0;
        }

        return (int) $node['Plan Rows'];
    }
}
