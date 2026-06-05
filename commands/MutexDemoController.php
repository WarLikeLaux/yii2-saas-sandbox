<?php

declare(strict_types=1);

namespace app\commands;

use app\jobs\ChatHistoryImportJob;
use app\services\MutexService;
use app\services\QueueService;
use yii\base\Module;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Демонстрация взаимных блокировок (mutex) и «single-flight» в очереди.
 *
 * ```
 * Поставить импорт в очередь:  ./yii mutex-demo/push <chatId>
 * Показать работу блокировки:  ./yii mutex-demo/demo <chatId>
 * Обработать очередь:          ./yii queue/listen
 * ```
 */
class MutexDemoController extends Controller
{
    /**
     * @var QueueService Сервис постановки фоновых задач в очередь
     */
    private $queueService;

    /**
     * @var MutexService Сервис взаимных блокировок
     */
    private $mutexService;

    /**
     * @param string $id Идентификатор контроллера
     * @param Module $module Модуль, которому принадлежит контроллер
     * @param QueueService $queueService Сервис постановки задач (внедряется контейнером)
     * @param MutexService $mutexService Сервис взаимных блокировок (внедряется контейнером)
     * @param array<string, mixed> $config Дополнительная конфигурация
     */
    public function __construct(
        string $id,
        Module $module,
        QueueService $queueService,
        MutexService $mutexService,
        array $config = []
    ) {
        $this->queueService = $queueService;
        $this->mutexService = $mutexService;
        parent::__construct($id, $module, $config);
    }

    /**
     * Ставит в очередь задачу импорта истории чата.
     *
     * @param int $chatId Идентификатор чата
     * @return int Код возврата (0 — успех)
     */
    public function actionPush(int $chatId = 42): int
    {
        $id = $this->queueService->push(new ChatHistoryImportJob(['chatId' => $chatId]));

        $this->stdout("Pushed import job #{$id} for chat {$chatId}\n");

        return ExitCode::OK;
    }

    /**
     * Наглядно показывает «single-flight»: пока один процесс держит блокировку
     * чата, повторный запуск импорта того же чата пропускается.
     *
     * @param int $chatId Идентификатор чата
     * @return int Код возврата (0 — успех)
     */
    public function actionDemo(int $chatId = 42): int
    {
        $key = 'import:chat:' . $chatId;

        $this->mutexService->acquire($key);
        $this->stdout("Воркер A: взял блокировку {$key}, импортирует историю...\n");

        $done = $this->mutexService->runExclusive($key, function () {
            $this->stdout("Воркер B: выполняет импорт\n");
        });

        $this->stdout($done
            ? "Воркер B: блокировка НЕ сработала (выполнил параллельно!)\n"
            : "Воркер B: блокировка занята — импорт пропущен (single-flight работает)\n");

        $this->mutexService->release($key);
        $this->stdout("Воркер A: импорт завершён, блокировка отпущена\n");

        return ExitCode::OK;
    }
}
