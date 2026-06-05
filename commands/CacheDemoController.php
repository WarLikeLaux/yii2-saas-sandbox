<?php

declare(strict_types=1);

namespace app\commands;

use app\services\CacheService;
use yii\base\Module;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Connection;

/**
 * Демонстрация кеширования cache-aside и защиты от cache stampede.
 *
 * Кеширует заведомо дорогой запрос — подсчёт всех сообщений (`COUNT(*)` по
 * миллиону строк). Первый вызов вычисляет значение (промах кеша), второй берёт
 * из кеша мгновенно.
 *
 * ```
 * ./yii cache-demo/count
 * ```
 */
class CacheDemoController extends Controller
{
    /**
     * @var CacheService Сервис кеширования
     */
    private $cacheService;

    /**
     * @var Connection Соединение с базой данных (для дорогого запроса-источника)
     */
    private $db;

    /**
     * @param string $id Идентификатор контроллера
     * @param Module $module Модуль, которому принадлежит контроллер
     * @param CacheService $cacheService Сервис кеширования (внедряется контейнером)
     * @param Connection $db Соединение с базой данных (внедряется контейнером)
     * @param array<string, mixed> $config Дополнительная конфигурация
     */
    public function __construct(
        string $id,
        Module $module,
        CacheService $cacheService,
        Connection $db,
        array $config = []
    ) {
        $this->cacheService = $cacheService;
        $this->db = $db;
        parent::__construct($id, $module, $config);
    }

    /**
     * Считает число сообщений через кеш: показывает время промаха и попадания.
     *
     * @return int Код возврата (0 — успех)
     */
    public function actionCount(): int
    {
        $db = $this->db;
        $producer = static function () use ($db): int {
            return (int) $db->createCommand('SELECT count(*) FROM {{%messages}}')->queryScalar();
        };

        $this->cacheService->invalidate('messages:count');

        $t1 = microtime(true);
        $v1 = $this->cacheService->remember('messages:count', 60, $producer);
        $d1 = (microtime(true) - $t1) * 1000;

        $t2 = microtime(true);
        $v2 = $this->cacheService->remember('messages:count', 60, $producer);
        $d2 = (microtime(true) - $t2) * 1000;

        $n1 = is_int($v1) ? $v1 : 0;
        $n2 = is_int($v2) ? $v2 : 0;

        $this->stdout(sprintf("1-й вызов (промах, считаем в БД): %d сообщений за %.1f мс\n", $n1, $d1));
        $this->stdout(sprintf("2-й вызов (из кеша):              %d сообщений за %.1f мс\n", $n2, $d2));

        return ExitCode::OK;
    }
}
