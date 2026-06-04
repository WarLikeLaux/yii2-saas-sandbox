<?php

declare(strict_types=1);

namespace app\commands;

use app\services\MessageStatusService;
use yii\base\Module;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Массовые операции над статусами сообщений.
 *
 * Имя `message-status`, а не `message`: последнее зарезервировано встроенной
 * командой Yii (извлечение переводов).
 *
 * ```
 * ./yii message-status/set 1 17 42 100   # выставить статус 1 сообщениям 17, 42, 100
 * ```
 */
class MessageStatusController extends Controller
{
    /**
     * @var MessageStatusService Сервис массового изменения статуса сообщений
     */
    private $statusService;

    /**
     * @param string $id Идентификатор контроллера
     * @param Module $module Модуль, которому принадлежит контроллер
     * @param MessageStatusService $statusService Сервис изменения статуса (внедряется контейнером)
     * @param array<string, mixed> $config Дополнительная конфигурация
     */
    public function __construct(string $id, Module $module, MessageStatusService $statusService, array $config = [])
    {
        $this->statusService = $statusService;
        parent::__construct($id, $module, $config);
    }

    /**
     * Массово проставляет статус указанным сообщениям.
     *
     * @param int $status Новое значение статуса
     * @param int ...$ids Идентификаторы сообщений
     * @return int Код возврата (0 — успех)
     */
    public function actionSet(int $status, int ...$ids): int
    {
        $affected = $this->statusService->markStatus($ids, $status);

        $this->stdout("Обновлено сообщений: {$affected}\n");

        return ExitCode::OK;
    }
}
