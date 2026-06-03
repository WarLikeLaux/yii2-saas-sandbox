<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Добавляет составной индекс `(chat_id, id)` к таблице `messages`.
 *
 * Покрывает keyset-пагинацию ленты чата: `WHERE chat_id = ? AND id < ?
 * ORDER BY id DESC`. Направление по `id` в индексе не указываем — B-tree
 * сканируется в обе стороны, поэтому `(chat_id, id)` обслуживает и `id DESC`.
 *
 * Индекс строится без блокировки записи (`CREATE INDEX CONCURRENTLY`), поэтому
 * используются `up()`/`down()` вместо `safeUp()`/`safeDown()`: `CONCURRENTLY`
 * не может выполняться внутри транзакции, в которую Yii оборачивает `safeUp()`.
 */
class m260603_140000_add_chat_id_index_to_messages extends Migration
{
    /**
     * Создаёт индекс конкурентно, без блокировки записи в таблицу.
     *
     * @return void
     */
    public function up(): void
    {
        $this->execute('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_messages_chat_id ON {{%messages}} (chat_id, id)');
    }

    /**
     * Удаляет индекс конкурентно.
     *
     * @return void
     */
    public function down(): void
    {
        $this->execute('DROP INDEX CONCURRENTLY IF EXISTS idx_messages_chat_id');
    }
}
