<?php

declare(strict_types=1);

namespace app\services;

use yii\db\Connection;

/**
 * Массовое изменение статуса сообщений с защитой от дедлоков.
 *
 * Когда несколько сообщений блокируются и обновляются в одной транзакции,
 * параллельные вызовы с пересекающимися наборами идентификаторов рискуют
 * образовать дедлок: один вызов уже держит строку A и ждёт B, другой держит B
 * и ждёт A. Лечится единым порядком захвата: идентификаторы сортируются по
 * возрастанию, и строки блокируются ровно этим порядком
 * (`SELECT ... ORDER BY id FOR UPDATE`) перед `UPDATE`. Раз порядок одинаков
 * для всех вызовов, кольцо ожидания не возникает. Транзакция короткая —
 * дружелюбна к PgBouncer (transaction mode).
 */
class MessageStatusService
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
     * Проставляет статус указанным сообщениям, блокируя строки в порядке id.
     *
     * Дубликаты идентификаторов отбрасываются, набор сортируется по возрастанию;
     * строки сначала блокируются этим же порядком, затем обновляются — всё в
     * одной транзакции. Подробности про порядок захвата — в описании класса.
     *
     * @param list<int> $ids Идентификаторы сообщений
     * @param int $status Новое значение статуса
     * @return int Сколько строк обновлено
     * @throws \Throwable Если обновление завершилось ошибкой
     */
    public function markStatus(array $ids, int $status): int
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);

        if ($ids === []) {
            return 0;
        }

        $placeholders = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $key = ':id' . $i;
            $placeholders[] = $key;
            $params[$key] = $id;
        }
        $in = implode(', ', $placeholders);

        $transaction = $this->db->beginTransaction();

        try {
            $this->db->createCommand(
                'SELECT id FROM {{%messages}} WHERE id IN (' . $in . ') ORDER BY id FOR UPDATE',
                $params
            )->queryColumn();

            $affected = $this->db->createCommand(
                'UPDATE {{%messages}} SET status = :status WHERE id IN (' . $in . ')',
                $params + [':status' => $status]
            )->execute();

            $transaction->commit();

            return $affected;
        } catch (\Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }
    }
}
