<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Включает триграммный поиск по тексту сообщений (`body`).
 *
 * Ставит расширение `pg_trgm` и строит GIN-индекс с классом операторов
 * `gin_trgm_ops`. Это ускоряет подстрочный поиск `body ILIKE '%...%'` (поиск
 * по чатам): селективный запрос уходит с полного скана таблицы на точечный
 * `Bitmap Index Scan`. Полнотекстовый индекс (`tsvector`) здесь не подходит —
 * он ищет по словам с морфологией, а нам нужна именно произвольная подстрока.
 *
 * Триграммы не покрывают паттерны короче трёх символов и вырожденные паттерны,
 * совпадающие почти со всеми строками, — там планировщик остаётся на скане.
 * GIN-индекс по `text` утяжеляет запись, поэтому перед массовым сидингом его
 * имеет смысл временно удалять и пересоздавать после загрузки.
 *
 * Индекс строится без блокировки записи (`CREATE INDEX CONCURRENTLY`), поэтому
 * используются `up()`/`down()` вместо `safeUp()`/`safeDown()`: `CONCURRENTLY`
 * не может выполняться внутри транзакции, в которую Yii оборачивает `safeUp()`.
 */
class m260603_170000_add_body_trgm_index_to_messages extends Migration
{
    /**
     * Ставит расширение и создаёт триграммный GIN-индекс, конкурентно.
     *
     * @return void
     */
    public function up(): void
    {
        $this->execute('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        $this->execute('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_messages_body_trgm ON {{%messages}} USING gin (body gin_trgm_ops)');
    }

    /**
     * Удаляет триграммный индекс и расширение, конкурентно.
     *
     * @return void
     */
    public function down(): void
    {
        $this->execute('DROP INDEX CONCURRENTLY IF EXISTS idx_messages_body_trgm');
        $this->execute('DROP EXTENSION IF EXISTS pg_trgm');
    }
}
