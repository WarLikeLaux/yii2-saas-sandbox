<?php

declare(strict_types=1);

namespace app\commands;

use yii\base\Module;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Connection;

/**
 * Демонстрация изоляции арендаторов через Row-Level Security.
 *
 * Вставляет тестовые сообщения двух тенантов (как суперпользователь, в обход
 * RLS), затем читает их под ограниченной ролью `tenant_user` с разным
 * `app.tenant_id` и показывает, что каждому тенанту видны только его строки.
 *
 * ```
 * ./yii rls-demo/show
 * ```
 */
class RlsDemoController extends Controller
{
    /**
     * Идентификатор тестового чата (чтобы не задевать рабочие данные).
     */
    private const TEST_CHAT_ID = 999888;

    /**
     * @var Connection Соединение с базой данных (внедряется контейнером)
     */
    private $db;

    /**
     * @param string $id Идентификатор контроллера
     * @param Module $module Модуль, которому принадлежит контроллер
     * @param Connection $db Соединение с базой данных (внедряется контейнером)
     * @param array<string, mixed> $config Дополнительная конфигурация
     */
    public function __construct(string $id, Module $module, Connection $db, array $config = [])
    {
        $this->db = $db;
        parent::__construct($id, $module, $config);
    }

    /**
     * Показывает, что под ролью арендатора видны только строки своего тенанта.
     *
     * @return int Код возврата (0 — успех)
     */
    public function actionShow(): int
    {
        $chatId = self::TEST_CHAT_ID;

        $this->db->createCommand('DELETE FROM {{%messages}} WHERE chat_id = :c', [':c' => $chatId])->execute();
        $this->db->createCommand(
            'INSERT INTO {{%messages}} (chat_id, user_id, body, status, created_at, tenant_id) VALUES
                (:c, 1, :b1, 0, :t1, 1),
                (:c, 1, :b2, 0, :t2, 1),
                (:c, 2, :b3, 0, :t3, 2)',
            [
                ':c' => $chatId,
                ':b1' => 'тенант 1 — a', ':t1' => '2026-06-05 10:00:00',
                ':b2' => 'тенант 1 — b', ':t2' => '2026-06-05 10:00:01',
                ':b3' => 'тенант 2 — a', ':t3' => '2026-06-05 10:00:02',
            ]
        )->execute();

        $all = (int) $this->db->createCommand(
            'SELECT count(*) FROM {{%messages}} WHERE chat_id = :c',
            [':c' => $chatId]
        )->queryScalar();

        $seenByTenant1 = $this->countAsTenant(1, $chatId);
        $seenByTenant2 = $this->countAsTenant(2, $chatId);

        $this->db->createCommand('DELETE FROM {{%messages}} WHERE chat_id = :c', [':c' => $chatId])->execute();

        $this->stdout("Всего тестовых строк (суперпользователь, RLS обходится): {$all}\n");
        $this->stdout("Видно роли tenant_user при app.tenant_id=1: {$seenByTenant1}\n");
        $this->stdout("Видно роли tenant_user при app.tenant_id=2: {$seenByTenant2}\n");

        return ExitCode::OK;
    }

    /**
     * Считает видимые строки тестового чата под ролью `tenant_user` и заданным
     * `app.tenant_id` (всё в одной транзакции — `SET LOCAL` PgBouncer-безопасен).
     *
     * @param int $tenantId Идентификатор арендатора
     * @param int $chatId Идентификатор тестового чата
     * @return int Сколько строк видит арендатор
     * @throws \Throwable Если запрос завершился ошибкой
     */
    private function countAsTenant(int $tenantId, int $chatId): int
    {
        $transaction = $this->db->beginTransaction();
        try {
            $this->db->createCommand('SET LOCAL ROLE tenant_user')->execute();
            $this->db->createCommand('SET LOCAL app.tenant_id = ' . $this->db->quoteValue((string) $tenantId))->execute();

            $count = (int) $this->db->createCommand(
                'SELECT count(*) FROM {{%messages}} WHERE chat_id = :c',
                [':c' => $chatId]
            )->queryScalar();

            $transaction->commit();

            return $count;
        } catch (\Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }
    }
}
