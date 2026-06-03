<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Создаёт таблицу `messages` для нагрузочных экспериментов.
 *
 * Вторичные индексы намеренно не создаются: сначала нужно увидеть медленные
 * запросы на большом объёме данных, а уже потом ускорить их отдельной миграцией
 * с индексами. Так разница «до/после» становится наглядной.
 */
class m260603_120000_create_messages_table extends Migration
{
    /**
     * Создаёт таблицу `messages` (только первичный ключ, без вторичных индексов).
     *
     * @return void
     */
    public function safeUp(): void
    {
        $this->createTable('{{%messages}}', [
            'id' => $this->bigPrimaryKey(),
            'chat_id' => $this->bigInteger()->notNull(),
            'user_id' => $this->bigInteger()->notNull(),
            'body' => $this->text()->notNull(),
            'status' => $this->smallInteger()->notNull()->defaultValue(0),
            'created_at' => $this->timestamp()->notNull(),
        ]);
    }

    /**
     * Удаляет таблицу `messages`.
     *
     * @return void
     */
    public function safeDown(): void
    {
        $this->dropTable('{{%messages}}');
    }
}
