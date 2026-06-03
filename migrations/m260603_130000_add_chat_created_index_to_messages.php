<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Добавляет составной индекс `(chat_id, created_at DESC, id DESC)` к `messages`.
 *
 * Покрывает ленту конкретного чата (`WHERE chat_id = ? ORDER BY created_at DESC,
 * id DESC`) и выбор последнего сообщения чата через боковое соединение
 * (`LATERAL ... LIMIT 1`) при построении списка чатов. Колонка `id` в индексе —
 * tiebreaker: при одинаковом `created_at` (точность до секунды) она задаёт
 * порядок и убирает доупорядочивающий `Sort`, поэтому `LIMIT 1` отдаёт строку
 * прямо из индекса.
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
        $this->execute('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_messages_chat_created ON {{%messages}} (chat_id, created_at DESC, id DESC)');
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
