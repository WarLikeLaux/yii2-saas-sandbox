<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Назначает роли БД защитные таймауты на уровне сервера.
 *
 * Под высокой нагрузкой зависший запрос или незакрытая транзакция держат
 * соединение и копят очередь. Эти таймауты обрывают аномально долгие операции
 * сами:
 *  - `statement_timeout` — предел на один запрос;
 *  - `lock_timeout` — сколько ждать блокировку строки, прежде чем сдаться;
 *  - `idle_in_transaction_session_timeout` — обрыв транзакции, забытой открытой.
 *
 * Значения вешаются на роль (`ALTER ROLE`), а не выставляются `SET` в коде,
 * потому что приложение ходит через PgBouncer в transaction mode: `SET` уровня
 * сессии там не переживает возврат соединения в пул, а настройки роли backend
 * получает при подключении. Применяются к новым соединениям, не к текущему.
 *
 * Значения — потолок для прикладных запросов, не для редких админ-операций:
 * долгий `CREATE INDEX`/`VACUUM` на больших данных при необходимости поднимает
 * лимит локально (`SET LOCAL statement_timeout = 0`).
 */
class m260603_190000_set_role_timeouts extends Migration
{
    /**
     * Назначает таймауты текущей роли БД.
     *
     * @return void
     */
    public function safeUp(): void
    {
        $this->execute("ALTER ROLE CURRENT_USER SET statement_timeout = '30s'");
        $this->execute("ALTER ROLE CURRENT_USER SET lock_timeout = '5s'");
        $this->execute("ALTER ROLE CURRENT_USER SET idle_in_transaction_session_timeout = '15s'");
    }

    /**
     * Снимает назначенные таймауты, возвращая значения по умолчанию.
     *
     * @return void
     */
    public function safeDown(): void
    {
        $this->execute('ALTER ROLE CURRENT_USER RESET statement_timeout');
        $this->execute('ALTER ROLE CURRENT_USER RESET lock_timeout');
        $this->execute('ALTER ROLE CURRENT_USER RESET idle_in_transaction_session_timeout');
    }
}
