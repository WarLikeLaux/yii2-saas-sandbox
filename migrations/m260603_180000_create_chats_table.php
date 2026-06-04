<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Денормализованная таблица `chats` со снимком последнего сообщения каждого чата.
 *
 * Список чатов раньше собирался из `messages` боковым перебором (`LATERAL`):
 * время росло линейно с числом чатов. Таблица `chats` хранит готовый снимок
 * последнего сообщения на каждый чат, поэтому список чатов читается простым
 * `ORDER BY last_message_at DESC LIMIT N` по индексу — стоимость не зависит ни
 * от числа чатов, ни от числа сообщений.
 *
 * Снимок поддерживается триггером уровня оператора (`FOR EACH STATEMENT`) с
 * переходной таблицей (`REFERENCING NEW TABLE`): он обрабатывает всю вставленную
 * пачку одним множественным запросом, а не построчно, — иначе массовая вставка
 * миллионов сообщений замедлилась бы на порядок. Триггер обновляет снимок только
 * если вставленное сообщение строго свежее текущего (`(created_at, id)`).
 */
class m260603_180000_create_chats_table extends Migration
{
    /**
     * Создаёт таблицу, индекс, триггер и наполняет снимок из существующих сообщений.
     *
     * @return void
     */
    public function safeUp(): void
    {
        $this->execute('CREATE TABLE {{%chats}} (
            id BIGINT PRIMARY KEY,
            last_message_id BIGINT NOT NULL,
            last_user_id BIGINT NOT NULL,
            last_body TEXT NOT NULL,
            last_message_at TIMESTAMP(0) NOT NULL
        )');

        $this->execute('CREATE INDEX idx_chats_last_message ON {{%chats}} (last_message_at DESC, last_message_id DESC)');

        $this->execute('INSERT INTO {{%chats}} (id, last_message_id, last_user_id, last_body, last_message_at)
            SELECT DISTINCT ON (chat_id) chat_id, id, user_id, body, created_at
            FROM {{%messages}}
            ORDER BY chat_id, created_at DESC, id DESC');

        $this->execute('CREATE FUNCTION app_refresh_chats() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                INSERT INTO ' . $this->db->quoteTableName('{{%chats}}') . ' AS c
                    (id, last_message_id, last_user_id, last_body, last_message_at)
                SELECT DISTINCT ON (n.chat_id) n.chat_id, n.id, n.user_id, n.body, n.created_at
                FROM new_messages n
                ORDER BY n.chat_id, n.created_at DESC, n.id DESC
                ON CONFLICT (id) DO UPDATE SET
                    last_message_id = EXCLUDED.last_message_id,
                    last_user_id = EXCLUDED.last_user_id,
                    last_body = EXCLUDED.last_body,
                    last_message_at = EXCLUDED.last_message_at
                WHERE (EXCLUDED.last_message_at, EXCLUDED.last_message_id) > (c.last_message_at, c.last_message_id);
                RETURN NULL;
            END;
            $$');

        $this->execute('CREATE TRIGGER trg_messages_refresh_chats
            AFTER INSERT ON {{%messages}}
            REFERENCING NEW TABLE AS new_messages
            FOR EACH STATEMENT EXECUTE FUNCTION app_refresh_chats()');
    }

    /**
     * Удаляет триггер, функцию и таблицу.
     *
     * @return void
     */
    public function safeDown(): void
    {
        $this->execute('DROP TRIGGER IF EXISTS trg_messages_refresh_chats ON {{%messages}}');
        $this->execute('DROP FUNCTION IF EXISTS app_refresh_chats()');
        $this->dropTable('{{%chats}}');
    }
}
