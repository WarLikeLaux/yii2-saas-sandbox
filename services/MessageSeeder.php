<?php

declare(strict_types=1);

namespace app\services;

use Faker\Factory;
use yii\db\Connection;

/**
 * Сервис массового наполнения таблицы `messages` тестовыми данными.
 *
 * Применяется для нагрузочных экспериментов: генерирует сообщения и вставляет
 * их пакетами (batch insert). Каждая пачка вставляется в отдельной транзакции —
 * короткие транзакции дружелюбны к PgBouncer (transaction mode) и не раздувают WAL.
 * Тексты сообщений берутся из заранее собранного Faker-пула связного русского
 * текста: генерация на лету для миллионов строк дорога, а пул даёт осмысленное
 * содержимое (по нему работает и поиск) без потери скорости вставки.
 */
class MessageSeeder
{
    /**
     * @var int Размер пула заранее сгенерированных текстов сообщений
     */
    private const BODY_POOL_SIZE = 2000;

    /**
     * @var Connection Соединение с базой данных
     */
    private $db;

    /**
     * @var list<string> Пул текстов сообщений, из которого берутся случайные body
     */
    private $bodyPool = [];

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
     * Снимок последнего сообщения в таблице `chats` отдельно не заполняется: его
     * автоматически поддерживает триггер на `messages` (см. миграцию создания
     * `chats`), срабатывающий на каждую вставленную пачку.
     *
     * По завершении вставки принудительно запускается `ANALYZE` обеих таблиц:
     * планировщик опирается на статистику, а выбор стратегии поиска в
     * `MessageFeed` читает оценку числа строк из плана — без свежей статистики
     * после массовой заливки оценка будет неверной, и запрос уйдёт в неоптимальную
     * ветку.
     *
     * @param int $total Сколько сообщений вставить
     * @param int $batchSize Размер одной пачки (число строк в одном INSERT)
     * @return void
     * @throws \Throwable Если вставка пачки завершилась ошибкой
     */
    public function seed(int $total, int $batchSize): void
    {
        $this->bootstrapBodyPool();

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

            $this->db->createCommand('ANALYZE ' . $this->db->quoteTableName('{{%messages}}'))->execute();
            $this->db->createCommand('ANALYZE ' . $this->db->quoteTableName('{{%chats}}'))->execute();
        } finally {
            $this->db->enableLogging = $enableLogging;
            $this->db->enableProfiling = $enableProfiling;
        }
    }

    /**
     * Полностью очищает `messages` и снимок `chats`, сбрасывает счётчик id.
     *
     * Триггер на `messages` обновляет `chats` только при вставке, а `TRUNCATE`
     * вставкой не является, поэтому снимок чистим явно в том же операторе —
     * иначе после перезаливки в `chats` остались бы записи прошлого прогона.
     *
     * @return void
     * @throws \yii\db\Exception Если очистка завершилась ошибкой
     */
    public function truncate(): void
    {
        $messages = $this->db->quoteTableName('{{%messages}}');
        $chats = $this->db->quoteTableName('{{%chats}}');
        $this->db->createCommand('TRUNCATE TABLE ' . $messages . ', ' . $chats . ' RESTART IDENTITY')->execute();
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
     * Заполняет пул текстов сообщений связным русским текстом через Faker.
     *
     * Вызывается один раз перед вставкой: `realText()` дорог, поэтому генерируем
     * фиксированный набор фраз заранее, а `makeRow()` берёт из него случайную —
     * скорость batch insert сохраняется, а содержимое остаётся осмысленным.
     *
     * @return void
     */
    private function bootstrapBodyPool(): void
    {
        $faker = Factory::create('ru_RU');

        $pool = [];
        for ($i = 0; $i < self::BODY_POOL_SIZE; $i++) {
            $pool[] = $faker->realText(mt_rand(20, 120));
        }

        $this->bodyPool = $pool;
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
            $this->bodyPool[mt_rand(0, count($this->bodyPool) - 1)],
            mt_rand(0, 2),
            date('Y-m-d H:i:s', time() - mt_rand(0, 31536000)),
        ];
    }
}
