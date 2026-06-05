<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Таблица `outbox` для транзакционной доставки событий (transactional outbox).
 *
 * Решает проблему «двойной записи» (dual write): нельзя атомарно и записать в
 * БД, и опубликовать сообщение в брокер (RabbitMQ) — если одно прошло, а второе
 * упало, состояния расходятся. Вместо прямого паблиша приложение в той же
 * транзакции, что и бизнес-запись, пишет событие в `outbox`. Отдельный
 * процесс-релей затем надёжно перекладывает необработанные события в очередь и
 * проставляет `processed_at`.
 *
 * Частичный индекс по `processed_at IS NULL` держит «хвост» необработанных
 * событий маленьким и дешёвым для сканирования релеем, не раздуваясь от
 * миллионов уже отправленных строк.
 */
class m260605_100000_create_outbox_table extends Migration
{
    /**
     * Создаёт таблицу outbox и частичный индекс по необработанным событиям.
     *
     * @return void
     */
    public function safeUp(): void
    {
        $this->execute('CREATE TABLE {{%outbox}} (
            id BIGSERIAL PRIMARY KEY,
            topic TEXT NOT NULL,
            payload TEXT NOT NULL,
            created_at TIMESTAMP(0) NOT NULL DEFAULT now(),
            processed_at TIMESTAMP(0)
        )');

        $this->execute('CREATE INDEX idx_outbox_unprocessed ON {{%outbox}} (id) WHERE processed_at IS NULL');
    }

    /**
     * Удаляет таблицу outbox.
     *
     * @return void
     */
    public function safeDown(): void
    {
        $this->execute('DROP TABLE {{%outbox}}');
    }
}
