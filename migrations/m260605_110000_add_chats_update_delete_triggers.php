<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Достраивает поддержку снимка `chats` для `UPDATE` и `DELETE` сообщений.
 *
 * Миграция `m260603_180000` поддерживала снимок только на `INSERT`. В реальном
 * мессенджере сообщения редактируют и удаляют, и без обработки этих событий
 * снимок начинает врать: после правки последнего сообщения в `chats` остаётся
 * старый текст превью, после удаления последнего — «битая» ссылка на
 * несуществующую строку.
 *
 * Здесь добавляются два триггера уровня оператора (как и `INSERT`-триггер — ради
 * массовых операций):
 *  - на `UPDATE`: если отредактировали именно последнее сообщение чата —
 *    обновить превью в снимке;
 *  - на `DELETE`: если удалили последнее сообщение чата — пересчитать снимок по
 *    предыдущему сообщению; а если в чате не осталось сообщений — убрать строку
 *    снимка.
 */
class m260605_110000_add_chats_update_delete_triggers extends Migration
{
    /**
     * Создаёт функции и триггеры на `UPDATE`/`DELETE` сообщений.
     *
     * @return void
     */
    public function safeUp(): void
    {
        $chats = $this->db->quoteTableName('{{%chats}}');
        $messages = $this->db->quoteTableName('{{%messages}}');

        $this->execute("CREATE FUNCTION app_refresh_chats_on_update() RETURNS trigger LANGUAGE plpgsql AS \$\$
            BEGIN
                UPDATE {$chats} c
                SET last_user_id = n.user_id,
                    last_body = n.body,
                    last_message_at = n.created_at
                FROM new_messages n
                WHERE c.id = n.chat_id AND c.last_message_id = n.id;
                RETURN NULL;
            END;
        \$\$");

        $this->execute("CREATE FUNCTION app_refresh_chats_on_delete() RETURNS trigger LANGUAGE plpgsql AS \$\$
            BEGIN
                UPDATE {$chats} c
                SET last_message_id = nl.id,
                    last_user_id = nl.user_id,
                    last_body = nl.body,
                    last_message_at = nl.created_at
                FROM (
                    SELECT DISTINCT ON (m.chat_id) m.chat_id, m.id, m.user_id, m.body, m.created_at
                    FROM {$messages} m
                    WHERE m.chat_id IN (
                        SELECT DISTINCT o.chat_id
                        FROM old_messages o
                        JOIN {$chats} cc ON cc.id = o.chat_id AND cc.last_message_id = o.id
                    )
                    ORDER BY m.chat_id, m.created_at DESC, m.id DESC
                ) nl
                WHERE c.id = nl.chat_id;

                DELETE FROM {$chats} c
                USING old_messages o
                WHERE c.id = o.chat_id
                  AND NOT EXISTS (SELECT 1 FROM {$messages} m WHERE m.chat_id = c.id);
                RETURN NULL;
            END;
        \$\$");

        $this->execute("CREATE TRIGGER trg_messages_refresh_chats_update
            AFTER UPDATE ON {$messages}
            REFERENCING NEW TABLE AS new_messages
            FOR EACH STATEMENT EXECUTE FUNCTION app_refresh_chats_on_update()");

        $this->execute("CREATE TRIGGER trg_messages_refresh_chats_delete
            AFTER DELETE ON {$messages}
            REFERENCING OLD TABLE AS old_messages
            FOR EACH STATEMENT EXECUTE FUNCTION app_refresh_chats_on_delete()");
    }

    /**
     * Удаляет триггеры и функции `UPDATE`/`DELETE`.
     *
     * @return void
     */
    public function safeDown(): void
    {
        $messages = $this->db->quoteTableName('{{%messages}}');

        $this->execute("DROP TRIGGER IF EXISTS trg_messages_refresh_chats_update ON {$messages}");
        $this->execute("DROP TRIGGER IF EXISTS trg_messages_refresh_chats_delete ON {$messages}");
        $this->execute('DROP FUNCTION IF EXISTS app_refresh_chats_on_update()');
        $this->execute('DROP FUNCTION IF EXISTS app_refresh_chats_on_delete()');
    }
}
