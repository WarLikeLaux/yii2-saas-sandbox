<?php

declare(strict_types=1);

namespace app\commands;

use app\services\OutboxService;
use yii\base\Module;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Connection;

/**
 * Демонстрация транзакционного outbox.
 *
 * ```
 * Записать события (в транзакции):  ./yii outbox/demo <сколько>
 * Переложить в очередь (релей):     ./yii outbox/relay
 * Обработать очередь:               ./yii queue/listen
 * ```
 */
class OutboxController extends Controller
{
    /**
     * @var Connection Соединение с базой данных (внедряется контейнером)
     */
    private $db;

    /**
     * @var OutboxService Сервис транзакционного outbox
     */
    private $outbox;

    /**
     * @param string $id Идентификатор контроллера
     * @param Module $module Модуль, которому принадлежит контроллер
     * @param Connection $db Соединение с базой данных (внедряется контейнером)
     * @param OutboxService $outbox Сервис outbox (внедряется контейнером)
     * @param array<string, mixed> $config Дополнительная конфигурация
     */
    public function __construct(
        string $id,
        Module $module,
        Connection $db,
        OutboxService $outbox,
        array $config = []
    ) {
        $this->db = $db;
        $this->outbox = $outbox;
        parent::__construct($id, $module, $config);
    }

    /**
     * Записывает события в outbox в одной транзакции с «бизнес-записью».
     *
     * @param int $count Сколько событий записать
     * @return int Код возврата (0 — успех)
     */
    public function actionDemo(int $count = 3): int
    {
        $transaction = $this->db->beginTransaction();
        try {
            for ($i = 1; $i <= $count; $i++) {
                $this->outbox->add('message.created', ['chat_id' => 42, 'text' => "событие #{$i}"]);
            }
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }

        $this->stdout("Записано в outbox в одной транзакции: {$count} событий\n");

        return ExitCode::OK;
    }

    /**
     * Перекладывает необработанные события из outbox в очередь.
     *
     * @return int Код возврата (0 — успех)
     */
    public function actionRelay(): int
    {
        $relayed = $this->outbox->relay();

        $this->stdout("Переложено из outbox в очередь: {$relayed} событий\n");

        return ExitCode::OK;
    }
}
