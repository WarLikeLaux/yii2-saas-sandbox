<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Изоляция арендаторов (multi-tenancy) на `messages` через Row-Level Security.
 *
 * В SaaS данные разных клиентов-арендаторов (тенантов) лежат в одних таблицах, и
 * утечка между ними недопустима. Полагаться на «не забудь `WHERE tenant_id`» в
 * каждом запросе ненадёжно. PostgreSQL Row-Level Security (RLS) переносит это на
 * уровень БД: политика автоматически подмешивает фильтр по тенанту в каждый
 * запрос — забыть его нельзя.
 *
 * Тенант берётся из настройки сессии `app.tenant_id`, которую приложение
 * выставляет на каждую транзакцию (`SET LOCAL app.tenant_id = ...`) — это
 * совместимо с PgBouncer в transaction mode.
 *
 * Важная оговорка: **суперпользователь обходит RLS**. Роль приложения `yii2` —
 * суперпользователь, поэтому для реальной изоляции заводится отдельная
 * non-superuser роль `tenant_user`; именно под ней RLS и действует. В проде
 * приложение работало бы под такой ограниченной ролью.
 */
class m260605_120000_add_tenant_rls extends Migration
{
    /**
     * Добавляет `tenant_id`, включает RLS, создаёт политику и роль арендатора.
     *
     * @return void
     */
    public function safeUp(): void
    {
        $messages = $this->db->quoteTableName('{{%messages}}');

        $this->execute("ALTER TABLE {$messages} ADD COLUMN tenant_id BIGINT NOT NULL DEFAULT 1");

        $this->execute("ALTER TABLE {$messages} ENABLE ROW LEVEL SECURITY");
        $this->execute("ALTER TABLE {$messages} FORCE ROW LEVEL SECURITY");

        $this->execute("CREATE POLICY tenant_isolation ON {$messages}
            USING (tenant_id = current_setting('app.tenant_id', true)::bigint)");

        $this->execute("DO \$\$
            BEGIN
                IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'tenant_user') THEN
                    CREATE ROLE tenant_user NOLOGIN;
                END IF;
            END
        \$\$");

        $this->execute("GRANT USAGE ON SCHEMA public TO tenant_user");
        $this->execute("GRANT SELECT, INSERT, UPDATE, DELETE ON {$messages} TO tenant_user");
    }

    /**
     * Снимает RLS, политику, роль и колонку `tenant_id`.
     *
     * @return void
     */
    public function safeDown(): void
    {
        $messages = $this->db->quoteTableName('{{%messages}}');

        $this->execute("DROP POLICY IF EXISTS tenant_isolation ON {$messages}");
        $this->execute("ALTER TABLE {$messages} NO FORCE ROW LEVEL SECURITY");
        $this->execute("ALTER TABLE {$messages} DISABLE ROW LEVEL SECURITY");
        $this->execute("REVOKE ALL ON {$messages} FROM tenant_user");
        $this->execute("REVOKE USAGE ON SCHEMA public FROM tenant_user");
        $this->execute('DROP ROLE IF EXISTS tenant_user');
        $this->execute("ALTER TABLE {$messages} DROP COLUMN tenant_id");
    }
}
