<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Добавляет индекс `(created_at, id)` к таблице `messages`.
 *
 * Нужен для сортировки и keyset-пагинации по времени: глобального списка чатов
 * (`ORDER BY created_at DESC, id DESC`) и ленты внутри чата. `id` в индексе —
 * tiebreaker: `created_at` не уникален, и пара `(created_at, id)` даёт строгий
 * стабильный порядок для курсора.
 *
 * Строится без блокировки записи (`CREATE INDEX CONCURRENTLY`), поэтому
 * используются `up()`/`down()` вместо `safeUp()`/`safeDown()`.
 */
class m260603_150000_add_created_at_index_to_messages extends Migration
{
    /**
     * Создаёт индекс конкурентно, без блокировки записи в таблицу.
     *
     * @return void
     */
    public function up(): void
    {
        $this->execute('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_messages_created ON {{%messages}} (created_at, id)');
    }

    /**
     * Удаляет индекс конкурентно.
     *
     * @return void
     */
    public function down(): void
    {
        $this->execute('DROP INDEX CONCURRENTLY IF EXISTS idx_messages_created');
    }
}
