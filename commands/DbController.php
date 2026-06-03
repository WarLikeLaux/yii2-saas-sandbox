<?php

declare(strict_types=1);

namespace app\commands;

use Yii;
use yii\console\Application;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Connection;

/**
 * Демонстрация исчерпания лимита соединений PostgreSQL без пула (PgBouncer).
 *
 * При `max_connections=5` параллельный запуск 10 воркеров приводит к тому, что
 * часть процессов не может открыть соединение и падает с ошибкой сервера
 * («too many clients already»). С пулом (PgBouncer) те же 10 клиентов
 * мультиплексировались бы на несколько реальных соединений без отказов.
 *
 * ```
 * ./yii db/storm           # 10 параллельных воркеров (db/ping)
 * ./yii db/storm 20 2000   # 20 воркеров, удержание соединения 2000 мс
 * ./yii db/ping            # один воркер: открыть соединение, SELECT, подержать
 * ```
 */
class DbController extends Controller
{
    /**
     * Один воркер: открывает соединение, делает быстрый SELECT и удерживает
     * соединение заданное время, чтобы параллельные воркеры пересеклись по
     * одновременно открытым соединениям.
     *
     * @param int $holdMs Сколько миллисекунд удерживать открытое соединение
     * @return int Код возврата: 0 — соединение открыто, 1 — отказ сервера
     */
    public function actionPing(int $holdMs = 1500): int
    {
        $app = Yii::$app;
        assert($app instanceof Application);

        /** @var Connection $db */
        $db = $app->get('db');

        try {
            $db->open();
            $db->createCommand('SELECT 1')->queryScalar();
            usleep($holdMs * 1000);
            $this->stdout("OK\n");

            return ExitCode::OK;
        } catch (\Throwable $e) {
            $this->stderr('FAIL: ' . $e->getMessage() . "\n");

            return ExitCode::UNSPECIFIED_ERROR;
        } finally {
            $db->close();
        }
    }

    /**
     * Оркестратор: запускает несколько воркеров (`db/ping`) параллельными
     * процессами и собирает, сколько соединений удалось открыть, а сколько
     * отвергнуто лимитом сервера.
     *
     * @param int $workers Сколько параллельных воркеров запустить
     * @param int $holdMs Сколько миллисекунд каждый воркер удерживает соединение
     * @return int Код возврата (0 — оркестратор отработал)
     */
    public function actionStorm(int $workers = 10, int $holdMs = 1500): int
    {
        $yii = Yii::getAlias('@app/yii');

        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($yii) . ' db/ping ' . $holdMs;

        $this->stdout("Запуск {$workers} параллельных воркеров (db/ping)...\n");

        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        /** @var list<array{process: resource, pipes: array<int, resource>}> $running */
        $running = [];
        for ($i = 0; $i < $workers; $i++) {
            $pipes = [];
            $process = proc_open($command, $descriptors, $pipes);

            if (!is_resource($process)) {
                $this->stderr("Не удалось запустить воркер #{$i}\n");
                continue;
            }

            $running[] = ['process' => $process, 'pipes' => $pipes];
        }

        $ok = 0;
        $failed = 0;

        /** @var array<string, int> $errors */
        $errors = [];

        foreach ($running as $worker) {
            $stderr = (string) stream_get_contents($worker['pipes'][2]);
            fclose($worker['pipes'][1]);
            fclose($worker['pipes'][2]);
            $code = proc_close($worker['process']);

            if ($code === 0) {
                $ok++;
            } else {
                $failed++;
                $message = trim($stderr);
                if ($message !== '') {
                    $errors[$message] = ($errors[$message] ?? 0) + 1;
                }
            }
        }

        $this->stdout(sprintf("Открыли соединение: %d из %d\n", $ok, $workers));
        $this->stdout(sprintf("Отклонено лимитом сервера: %d\n", $failed));

        foreach ($errors as $message => $count) {
            $this->stdout(sprintf("  [%d x] %s\n", $count, $message));
        }

        return ExitCode::OK;
    }
}
