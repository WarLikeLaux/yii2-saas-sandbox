<?php

declare(strict_types=1);

namespace app\jobs;

use app\services\MutexService;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

/**
 * Фоновый импорт истории переписки чата с защитой от повторного запуска.
 *
 * Импорт — тяжёлая операция (десятки тысяч запросов к внешнему API). Очередь
 * доставляет задачи по принципу at-least-once, а повторы могут запустить один
 * и тот же импорт дважды. Идемпотентность данных уберегает от дублей строк, но
 * НЕ от двойной работы. Поэтому импорт оборачивается во взаимную блокировку
 * (`MutexService`) по ключу чата: пока импорт идёт, повторный запуск для того
 * же чата пропускается («single-flight»).
 *
 * ```
 * Поставить:  ./yii mutex-demo/push <chatId>
 * Обработать: ./yii queue/listen
 * ```
 */
class ChatHistoryImportJob extends BaseObject implements JobInterface
{
    /**
     * @var int Идентификатор чата, историю которого импортируем
     */
    public $chatId;

    /**
     * Выполняет импорт под взаимной блокировкой; повторный параллельный запуск
     * для того же чата пропускается.
     *
     * @param \yii\queue\Queue $queue Очередь, из которой пришла задача
     * @return void
     */
    public function execute($queue)
    {
        $chatId = $this->chatId;

        /** @var MutexService $mutex */
        $mutex = Yii::$container->get(MutexService::class);

        $done = $mutex->runExclusive('import:chat:' . $chatId, static function () use ($chatId) {
            Yii::info("Импорт истории чата {$chatId}", 'import');
        });

        fwrite(STDOUT, $done
            ? "[import] chat {$chatId}: импорт выполнен\n"
            : "[import] chat {$chatId}: уже идёт, пропуск\n");
    }
}
