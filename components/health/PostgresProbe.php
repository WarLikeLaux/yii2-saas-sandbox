<?php

declare(strict_types=1);

namespace app\components\health;

use yii\db\Connection;

/**
 * Проба доступности PostgreSQL.
 *
 * Проверяет соединение с базой данных, запрашивая версию сервера.
 */
class PostgresProbe implements ProbeInterface
{
    /**
     * @var Connection Соединение с базой данных
     */
    private $db;

    /**
     * @param Connection $db Соединение с базой данных (внедряется контейнером)
     */
    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * Возвращает имя сервиса.
     *
     * @return string Имя сервиса
     */
    public function name(): string
    {
        return 'PostgreSQL';
    }

    /**
     * Запрашивает версию сервера PostgreSQL.
     *
     * @return string Строка версии PostgreSQL
     * @throws \Throwable Если соединение с БД не удалось
     */
    public function check(): string
    {
        return (string) $this->db->createCommand('SELECT version()')->queryScalar();
    }
}
