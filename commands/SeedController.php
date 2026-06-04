<?php

declare(strict_types=1);

namespace app\commands;

use app\services\MessageSeeder;
use yii\base\Module;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Наполнение базы тестовыми данными для нагрузочных экспериментов.
 *
 * ```
 * ./yii seed/messages                          # 1 000 000 строк пачками по 1000
 * ./yii seed/messages 100000 5000              # 100 000 строк пачками по 5000
 * ./yii seed/messages 0 0 --truncate           # только очистить таблицу messages
 * ./yii seed/messages 1000000 2000 --truncate  # очистить и залить заново
 * ```
 */
class SeedController extends Controller
{
    /**
     * @var bool Очистить таблицу перед вставкой (опция `--truncate`)
     */
    public $truncate = false;

    /**
     * @var bool Грузить через `COPY` вместо batch `INSERT` (опция `--copy`)
     */
    public $copy = false;

    /**
     * @var MessageSeeder Сервис массовой вставки сообщений
     */
    private $seeder;

    /**
     * @param string $id Идентификатор контроллера
     * @param Module $module Модуль, которому принадлежит контроллер
     * @param MessageSeeder $seeder Сервис наполнения (внедряется контейнером)
     * @param array<string, mixed> $config Дополнительная конфигурация
     */
    public function __construct(string $id, Module $module, MessageSeeder $seeder, array $config = [])
    {
        $this->seeder = $seeder;
        parent::__construct($id, $module, $config);
    }

    /**
     * Объявляет глобальные опции, доступные действиям контроллера.
     *
     * @param string $actionID Идентификатор действия
     * @return list<string> Имена доступных опций
     */
    public function options($actionID): array
    {
        return ['truncate', 'copy'];
    }

    /**
     * Генерирует и вставляет сообщения в таблицу messages, замеряя скорость.
     *
     * При `--truncate` таблица сначала очищается. Если число сообщений равно 0,
     * вставка пропускается (удобно для режима «только очистить»).
     *
     * @param int $total Сколько сообщений вставить (0 — пропустить вставку)
     * @param int $batchSize Размер пачки (число строк в одном INSERT)
     * @return int Код возврата (0 — успех)
     */
    public function actionMessages(int $total = 1000000, int $batchSize = 1000): int
    {
        if ($this->truncate) {
            $this->seeder->truncate();
            $this->stdout("Таблица messages очищена.\n");
        }

        if ($total <= 0) {
            return ExitCode::OK;
        }

        if ($batchSize < 1) {
            $this->stderr("Размер пачки должен быть не меньше 1.\n");

            return ExitCode::USAGE;
        }

        $this->stdout("Вставка {$total} сообщений пачками по {$batchSize}...\n");

        $start = microtime(true);
        $this->seeder->seed($total, $batchSize, $this->copy);
        $elapsed = microtime(true) - $start;

        $rate = $elapsed > 0 ? (int) round($total / $elapsed) : 0;

        $this->stdout(sprintf(
            "Готово: %d строк за %.2f c (%d строк/с)\n",
            $total,
            $elapsed,
            $rate
        ));

        return ExitCode::OK;
    }
}
