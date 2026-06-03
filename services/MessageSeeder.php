<?php

declare(strict_types=1);

namespace app\services;

use yii\db\Connection;

/**
 * Сервис массового наполнения таблицы `messages` тестовыми данными.
 *
 * Применяется для нагрузочных экспериментов: генерирует сообщения и вставляет
 * их пакетами (batch insert). Каждая пачка вставляется в отдельной транзакции —
 * короткие транзакции дружелюбны к PgBouncer (transaction mode) и не раздувают WAL.
 */
class MessageSeeder
{
    /**
     * @var Connection Соединение с базой данных
     */
    private $db;

    /**
     * @var list<string> Имена колонок таблицы `messages`, заполняемых при вставке
     */
    private $columns = ['chat_id', 'user_id', 'body', 'status', 'created_at'];

    /**
     * @param Connection $db Соединение с базой данных (внедряется контейнером)
     */
    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * Генерирует и вставляет заданное число сообщений пачками.
     *
     * На время вставки логирование и профилирование запросов БД отключаются и
     * восстанавливаются по завершении: при миллионах строк сбор полного текста
     * каждого (огромного) batch-INSERT в логгер/профайлер впустую съедает память
     * вплоть до её исчерпания. Прежние значения флагов восстанавливаются всегда.
     *
     * @param int $total Сколько сообщений вставить
     * @param int $batchSize Размер одной пачки (число строк в одном INSERT)
     * @return void
     * @throws \Throwable Если вставка пачки завершилась ошибкой
     */
    public function seed(int $total, int $batchSize): void
    {
        $enableLogging = $this->db->enableLogging;
        $enableProfiling = $this->db->enableProfiling;
        $this->db->enableLogging = false;
        $this->db->enableProfiling = false;

        try {
            $batch = [];

            for ($i = 1; $i <= $total; $i++) {
                $batch[] = $this->makeRow();

                if (count($batch) >= $batchSize) {
                    $this->insertBatch($batch);
                    $batch = [];
                }
            }

            if ($batch !== []) {
                $this->insertBatch($batch);
            }
        } finally {
            $this->db->enableLogging = $enableLogging;
            $this->db->enableProfiling = $enableProfiling;
        }
    }

    /**
     * Полностью очищает таблицу `messages` и сбрасывает счётчик идентификаторов.
     *
     * @return void
     * @throws \yii\db\Exception Если очистка завершилась ошибкой
     */
    public function truncate(): void
    {
        $table = $this->db->quoteTableName('{{%messages}}');
        $this->db->createCommand('TRUNCATE TABLE ' . $table . ' RESTART IDENTITY')->execute();
    }

    /**
     * Вставляет одну пачку строк в единой транзакции.
     *
     * @param list<array{0: int, 1: int, 2: string, 3: int, 4: string}> $rows Строки для вставки
     * @return void
     * @throws \Throwable Если вставка завершилась ошибкой
     */
    private function insertBatch(array $rows): void
    {
        $transaction = $this->db->beginTransaction();

        try {
            $this->db->createCommand()->batchInsert('{{%messages}}', $this->columns, $rows)->execute();
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }
    }

    /**
     * Создаёт одну случайную строку сообщения.
     *
     * @return array{0: int, 1: int, 2: string, 3: int, 4: string} Значения колонок в порядке self::$columns
     */
    private function makeRow(): array
    {
        return [
            mt_rand(1, 1000),
            mt_rand(1, 500),
            'Сообщение №' . mt_rand(1, 1000000),
            mt_rand(0, 2),
            date('Y-m-d H:i:s', time() - mt_rand(0, 31536000)),
        ];
    }
}
