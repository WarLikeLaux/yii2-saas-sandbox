<?php

declare(strict_types=1);

namespace app\commands;

use app\components\CorrelationContext;
use Yii;
use yii\base\Module;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Демонстрация структурного JSON-логирования с correlation id.
 *
 * Пишет сообщение в лог под текущим идентификатором корреляции и показывает
 * получившуюся JSON-строку из файла `@runtime/logs/app.json.log` — именно такие
 * строки собирает filebeat и индексирует Elasticsearch для Kibana.
 *
 * ```
 * ./yii log-demo/write "обработка сообщения"
 * ```
 */
class LogDemoController extends Controller
{
    /**
     * @var CorrelationContext Контекст корреляции
     */
    private $correlation;

    /**
     * @param string $id Идентификатор контроллера
     * @param Module $module Модуль, которому принадлежит контроллер
     * @param CorrelationContext $correlation Контекст корреляции (внедряется контейнером)
     * @param array<string, mixed> $config Дополнительная конфигурация
     */
    public function __construct(
        string $id,
        Module $module,
        CorrelationContext $correlation,
        array $config = []
    ) {
        $this->correlation = $correlation;
        parent::__construct($id, $module, $config);
    }

    /**
     * Пишет сообщение в лог и печатает его JSON-строку с correlation id.
     *
     * @param string $message Текст сообщения
     * @return int Код возврата (0 — успех)
     */
    public function actionWrite(string $message = 'обработка сообщения'): int
    {
        $correlationId = $this->correlation->ensure();
        Yii::info($message, 'demo');
        Yii::getLogger()->flush(true);

        $this->stdout("correlation_id: {$correlationId}\n");

        $file = Yii::getAlias('@runtime/logs/app.json.log');
        if (is_file($file)) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (is_array($lines) && $lines !== []) {
                $last = end($lines);
                $this->stdout("последняя JSON-строка лога:\n{$last}\n");
            }
        }

        return ExitCode::OK;
    }
}
