<?php

declare(strict_types=1);

namespace app\services;

use app\jobs\DemoJob;
use yii\db\Connection;
use yii\db\Expression;

/**
 * Транзакционный outbox: надёжная доставка событий из БД в очередь.
 *
 * Прямой паблиш в брокер прямо из бизнес-кода ненадёжен: запись в БD и
 * публикация в RabbitMQ — две разные системы, атомарно их не закоммитить
 * («dual write»). Если БД зафиксировала изменение, а паблиш упал (или
 * наоборот) — состояния расходятся.
 *
 * Решение: {@see add()} пишет событие в таблицу `outbox` **в той же
 * транзакции**, что и бизнес-запись (транзакцию открывает вызывающий код) —
 * значит, либо зафиксированы оба, либо ни одного. Затем {@see relay()}
 * (отдельный процесс) перекладывает необработанные события в очередь. Релей
 * берёт строки `FOR UPDATE SKIP LOCKED`, поэтому несколько релеев могут
 * работать параллельно, не наступая друг другу. Доставка получается
 * at-least-once — потребитель обязан быть идемпотентным.
 */
class OutboxService
{
    /**
     * @var Connection Соединение с базой данных (внедряется контейнером)
     */
    private $db;

    /**
     * @var QueueService Сервис постановки задач в очередь
     */
    private $queue;

    /**
     * @param Connection $db Соединение с базой данных
     * @param QueueService $queue Сервис постановки задач в очередь
     */
    public function __construct(Connection $db, QueueService $queue)
    {
        $this->db = $db;
        $this->queue = $queue;
    }

    /**
     * Записывает событие в outbox. Вызывать **внутри** транзакции бизнес-записи.
     *
     * @param string $topic Тип события (например, `message.created`)
     * @param array<string, mixed> $payload Полезная нагрузка события
     * @return void
     * @throws \RuntimeException Если payload не сериализуется в JSON
     */
    public function add(string $topic, array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('Не удалось сериализовать payload outbox-события');
        }

        $this->db->createCommand()
            ->insert('{{%outbox}}', ['topic' => $topic, 'payload' => $json])
            ->execute();
    }

    /**
     * Перекладывает необработанные события из outbox в очередь.
     *
     * Берёт пачку необработанных строк `FOR UPDATE SKIP LOCKED` (дружелюбно к
     * параллельным релеям), публикует каждую в очередь и проставляет
     * `processed_at` — всё в одной транзакции.
     *
     * @param int $limit Сколько событий обработать за один проход
     * @return int Сколько событий переложено в очередь
     * @throws \Throwable Если публикация или обновление завершились ошибкой
     */
    public function relay(int $limit = 100): int
    {
        $transaction = $this->db->beginTransaction();

        try {
            $rows = $this->db->createCommand(
                'SELECT id, topic, payload FROM {{%outbox}}
                 WHERE processed_at IS NULL
                 ORDER BY id
                 LIMIT :lim
                 FOR UPDATE SKIP LOCKED',
                [':lim' => $limit]
            )->queryAll();

            $ids = [];
            foreach ($rows as $row) {
                $topic = isset($row['topic']) && is_string($row['topic']) ? $row['topic'] : '';
                $payload = isset($row['payload']) && is_string($row['payload']) ? $row['payload'] : '';
                $this->queue->push(new DemoJob(['message' => $topic . ' ' . $payload]));
                $ids[] = isset($row['id']) ? intval($row['id']) : 0;
            }

            if ($ids !== []) {
                $this->db->createCommand()
                    ->update('{{%outbox}}', ['processed_at' => new Expression('now()')], ['id' => $ids])
                    ->execute();
            }

            $transaction->commit();

            return count($ids);
        } catch (\Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }
    }
}
