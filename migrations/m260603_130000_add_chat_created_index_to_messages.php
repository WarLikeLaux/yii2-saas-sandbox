<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Добавляет составной индекс `(chat_id, created_at)` к таблице `messages`.
 *
 * Ускоряет выборку сообщений конкретного чата с сортировкой/фильтром по дате.
 *
 * Индекс строится без блокировки записи (`CREATE INDEX CONCURRENTLY`), поэтому
 * миграция использует `up()`/`down()` вместо `safeUp()`/`safeDown()`: команда
 * `CONCURRENTLY` не может выполняться внутри транзакции, в которую Yii по
 * умолчанию оборачивает `safeUp()`.
 */
class m260603_130000_add_chat_created_index_to_messages extends Migration
{
    /**
     * Создаёт индекс конкурентно, без блокировки записи в таблицу.
     *
     * @return void
     */
    public function up(): void
    {
        $this->execute('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_messages_chat_created ON {{%messages}} (chat_id, created_at)');
    }

    /**
     * Удаляет индекс конкурентно.
     *
     * @return void
     */
    public function down(): void
    {
        $this->execute('DROP INDEX CONCURRENTLY IF EXISTS idx_messages_chat_created');
    }
}
