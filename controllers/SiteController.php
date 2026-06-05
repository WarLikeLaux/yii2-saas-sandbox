<?php

declare(strict_types=1);

namespace app\controllers;

use app\components\CorrelationContext;
use app\jobs\DemoJob;
use app\services\CacheService;
use app\services\CircuitBreakerService;
use app\services\MutexService;
use app\services\OutboxService;
use app\services\QueueService;
use app\services\RateLimiterService;
use app\services\SentryService;
use Yii;
use yii\db\Connection;
use yii\web\Controller;
use yii\web\ErrorAction;
use yii\web\Response;

/**
 * Публичная часть сайта песочницы.
 *
 * Главная — приборная панель стенда: ссылки на сервисы (лента, health, Kibana,
 * RabbitMQ, Adminer) и запуск приёмов высоких нагрузок прямо из браузера
 * (`actionPlay`), чтобы можно было поиграться, не заходя в консоль.
 */
class SiteController extends Controller
{
    /**
     * Объявляет внешние действия контроллера.
     *
     * @return array<string, array{class: class-string}> Карта действий контроллера
     */
    public function actions(): array
    {
        return [
            'error' => [
                'class' => ErrorAction::class,
            ],
        ];
    }

    /**
     * Отдаёт главную страницу-дашборд песочницы.
     *
     * @return string HTML-разметка главной страницы
     */
    public function actionIndex(): string
    {
        return $this->render('index');
    }

    /**
     * Запускает выбранный приём-демо и возвращает на главную с результатом.
     *
     * @return Response Редирект на главную (результат — во flash-сообщении)
     */
    public function actionPlay(): Response
    {
        $raw = $this->request->post('demo');
        $demo = is_string($raw) ? $raw : '';

        $titles = [
            'cache' => 'Кеш + защита от stampede',
            'rate' => 'Rate limiting (token bucket)',
            'breaker' => 'Circuit breaker',
            'mutex' => 'Mutex / single-flight',
            'outbox' => 'Transactional outbox',
            'rls' => 'Multi-tenancy (RLS)',
            'log' => 'Структурный лог + correlation id',
            'sentry' => 'Sentry',
            'queue' => 'Очередь (RabbitMQ)',
        ];

        if (!isset($titles[$demo])) {
            $this->flashPlay('Неизвестный приём', $demo, false);

            return $this->redirect(['site/index', '#' => 'playground']);
        }

        try {
            $output = $this->runDemo($demo);
            $ok = true;
        } catch (\Throwable $e) {
            $output = get_class($e) . ': ' . $e->getMessage();
            $ok = false;
        }

        $this->flashPlay($titles[$demo], $output, $ok);

        return $this->redirect(['site/index', '#' => 'playground']);
    }

    /**
     * Кладёт результат приёма во flash-сообщение для показа на главной.
     *
     * @param string $title Заголовок приёма
     * @param string $output Текст результата
     * @param bool $ok Успешно ли выполнено
     */
    private function flashPlay(string $title, string $output, bool $ok): void
    {
        $app = Yii::$app;
        if ($app === null) {
            return;
        }
        $session = $app->session;
        if ($session instanceof \yii\web\Session) {
            $session->setFlash('play', ['title' => $title, 'output' => $output, 'ok' => $ok]);
        }
    }

    /**
     * Выполняет приём по ключу и возвращает текстовый результат.
     *
     * @param string $demo Ключ приёма
     * @return string Текст результата для показа на странице
     * @throws \Throwable Если приём завершился ошибкой
     */
    private function runDemo(string $demo): string
    {
        switch ($demo) {
            case 'cache':
                return $this->playCache();
            case 'rate':
                return $this->playRate();
            case 'breaker':
                return $this->playBreaker();
            case 'mutex':
                return $this->playMutex();
            case 'outbox':
                return $this->playOutbox();
            case 'rls':
                return $this->playRls();
            case 'log':
                return $this->playLog();
            case 'sentry':
                return $this->playSentry();
            case 'queue':
                return $this->playQueue();
            default:
                return '';
        }
    }

    /**
     * Кеш cache-aside: время промаха против попадания.
     *
     * @return string Результат демо
     */
    private function playCache(): string
    {
        /** @var CacheService $cache */
        $cache = Yii::$container->get(CacheService::class);
        /** @var Connection $db */
        $db = Yii::$container->get(Connection::class);

        $producer = static function () use ($db): int {
            return (int) $db->createCommand('SELECT count(*) FROM {{%messages}}')->queryScalar();
        };

        $cache->invalidate('messages:count');
        $t1 = microtime(true);
        $v1 = $cache->remember('messages:count', 60, $producer);
        $d1 = (microtime(true) - $t1) * 1000;
        $t2 = microtime(true);
        $cache->remember('messages:count', 60, $producer);
        $d2 = (microtime(true) - $t2) * 1000;

        $count = is_int($v1) ? $v1 : 0;

        return sprintf(
            "Сообщений: %d\n1-й вызов (промах, считаем в БД): %.1f мс\n2-й вызов (из кеша):              %.1f мс",
            $count,
            $d1,
            $d2
        );
    }

    /**
     * Token bucket: сколько из 10 запросов прошло.
     *
     * @return string Результат демо
     */
    private function playRate(): string
    {
        /** @var RateLimiterService $limiter */
        $limiter = Yii::$container->get(RateLimiterService::class);

        $key = 'web-demo-' . random_int(1000, 9999);
        $allowed = 0;
        $denied = 0;
        for ($i = 0; $i < 10; $i++) {
            if ($limiter->allow($key, 5, 1.0)) {
                $allowed++;
            } else {
                $denied++;
            }
        }

        return "Ведро=5, пополнение=1/с\nИз 10 запросов: разрешено {$allowed}, отклонено {$denied}";
    }

    /**
     * Circuit breaker: серия отказов размыкает цепь.
     *
     * @return string Результат демо
     */
    private function playBreaker(): string
    {
        /** @var CircuitBreakerService $breaker */
        $breaker = Yii::$container->get(CircuitBreakerService::class);

        $key = 'web-demo-' . random_int(1000, 9999);
        $lines = ['Старт: доступен = ' . $this->yesNo($breaker->isAvailable($key))];
        for ($i = 1; $i <= 5; $i++) {
            $breaker->recordFailure($key);
            $lines[] = "После отказа #{$i}: доступен = " . $this->yesNo($breaker->isAvailable($key));
        }
        $breaker->recordSuccess($key);
        $lines[] = 'После восстановления: доступен = ' . $this->yesNo($breaker->isAvailable($key));

        return implode("\n", $lines);
    }

    /**
     * Single-flight: второй заход за ту же блокировку пропускается.
     *
     * @return string Результат демо
     */
    private function playMutex(): string
    {
        /** @var MutexService $mutex */
        $mutex = Yii::$container->get(MutexService::class);

        $key = 'web-demo-' . random_int(1000, 9999);
        $mutex->acquire($key);
        $done = $mutex->runExclusive($key, static function (): void {
        });
        $mutex->release($key);

        return $done
            ? 'Воркер A держит блокировку, воркер B: блокировка НЕ сработала'
            : "Воркер A держит блокировку\nВоркер B: блокировка занята — работа пропущена (single-flight)";
    }

    /**
     * Transactional outbox: запись событий в транзакции и перекладка в очередь.
     *
     * @return string Результат демо
     */
    private function playOutbox(): string
    {
        /** @var OutboxService $outbox */
        $outbox = Yii::$container->get(OutboxService::class);
        /** @var Connection $db */
        $db = Yii::$container->get(Connection::class);

        $transaction = $db->beginTransaction();
        try {
            for ($i = 1; $i <= 3; $i++) {
                $outbox->add('message.created', ['chat_id' => 42, 'text' => "событие #{$i}"]);
            }
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }

        $relayed = $outbox->relay();

        return "Записано в outbox в одной транзакции: 3 события\nПереложено в очередь релеем: {$relayed}";
    }

    /**
     * Multi-tenancy: под ролью tenant_user видны только строки своего тенанта.
     *
     * @return string Результат демо
     */
    private function playRls(): string
    {
        /** @var Connection $db */
        $db = Yii::$container->get(Connection::class);

        $chatId = 999888;
        $db->createCommand('DELETE FROM {{%messages}} WHERE chat_id = :c', [':c' => $chatId])->execute();
        $db->createCommand(
            'INSERT INTO {{%messages}} (chat_id, user_id, body, status, created_at, tenant_id) VALUES
                (:c, 1, :b1, 0, :t1, 1), (:c, 1, :b2, 0, :t2, 1), (:c, 2, :b3, 0, :t3, 2)',
            [
                ':c' => $chatId,
                ':b1' => 'тенант 1 a', ':t1' => '2026-06-05 10:00:00',
                ':b2' => 'тенант 1 b', ':t2' => '2026-06-05 10:00:01',
                ':b3' => 'тенант 2 a', ':t3' => '2026-06-05 10:00:02',
            ]
        )->execute();

        $all = (int) $db->createCommand('SELECT count(*) FROM {{%messages}} WHERE chat_id = :c', [':c' => $chatId])->queryScalar();
        $t1 = $this->countAsTenant($db, 1, $chatId);
        $t2 = $this->countAsTenant($db, 2, $chatId);

        $db->createCommand('DELETE FROM {{%messages}} WHERE chat_id = :c', [':c' => $chatId])->execute();

        return "Всего тестовых строк (superuser, RLS обходится): {$all}\nВидно тенанту 1: {$t1}\nВидно тенанту 2: {$t2}";
    }

    /**
     * Структурный лог: пишет строку и показывает её JSON с correlation id.
     *
     * @return string Результат демо
     */
    private function playLog(): string
    {
        /** @var CorrelationContext $correlation */
        $correlation = Yii::$container->get(CorrelationContext::class);

        $correlationId = $correlation->ensure();
        Yii::info('демо-событие из веб-дашборда', 'demo');
        Yii::getLogger()->flush(true);

        $line = '(лог-файл ещё не создан)';
        $file = Yii::getAlias('@runtime/logs/app.json.log');
        if (is_file($file)) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (is_array($lines) && $lines !== []) {
                $line = end($lines);
            }
        }

        return "correlation_id: {$correlationId}\nПоследняя JSON-строка лога:\n{$line}";
    }

    /**
     * Sentry: показывает, включён ли он (зависит от SENTRY_DSN).
     *
     * @return string Результат демо
     */
    private function playSentry(): string
    {
        /** @var SentryService $sentry */
        $sentry = Yii::$container->get(SentryService::class);

        if (!$sentry->isEnabled()) {
            return 'Sentry отключён (SENTRY_DSN пуст) — события никуда не уходят. Задайте SENTRY_DSN для отправки.';
        }

        $sentry->captureMessage('Тестовое событие из веб-дашборда');

        return 'Тестовое событие отправлено в Sentry.';
    }

    /**
     * Очередь: ставит фоновую задачу в RabbitMQ.
     *
     * @return string Результат демо
     */
    private function playQueue(): string
    {
        /** @var QueueService $queue */
        $queue = Yii::$container->get(QueueService::class);

        $id = $queue->push(new DemoJob(['message' => 'привет из веб-дашборда']));

        return "Задача поставлена в очередь: #{$id}\nОбработать воркером: ./yii queue/listen";
    }

    /**
     * Считает видимые строки тестового чата под ролью tenant_user.
     *
     * @param Connection $db Соединение с БД
     * @param int $tenantId Идентификатор тенанта
     * @param int $chatId Идентификатор тестового чата
     * @return int Сколько строк видит тенант
     * @throws \Throwable Если запрос завершился ошибкой
     */
    private function countAsTenant(Connection $db, int $tenantId, int $chatId): int
    {
        $transaction = $db->beginTransaction();
        try {
            $db->createCommand('SET LOCAL ROLE tenant_user')->execute();
            $db->createCommand('SET LOCAL app.tenant_id = ' . $db->quoteValue((string) $tenantId))->execute();
            $count = (int) $db->createCommand(
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

    /**
     * Превращает булево в «да»/«нет».
     *
     * @param bool $value Значение
     * @return string «да» или «нет»
     */
    private function yesNo(bool $value): string
    {
        return $value ? 'да' : 'нет';
    }
}
