<?php

declare(strict_types=1);

namespace app\controllers;

use app\components\HealthCheckerInterface;
use Yii;
use yii\base\Module;
use yii\web\Controller;
use yii\web\Response;

/**
 * Страница состояния инфраструктуры.
 *
 * GET /health               — человекочитаемая HTML-страница
 * GET /health?format=json   — машинный JSON (для мониторинга / проб)
 *
 * HTTP-код: 200, если всё в порядке, иначе 503 Service Unavailable.
 */
class HealthController extends Controller
{
    /**
     * @var HealthCheckerInterface Компонент проверки связности инфраструктуры
     */
    private $healthChecker;

    /**
     * @param string $id Идентификатор контроллера
     * @param Module $module Модуль, которому принадлежит контроллер
     * @param HealthCheckerInterface $healthChecker Компонент проверки (внедряется контейнером)
     * @param array<string, mixed> $config Дополнительная конфигурация
     */
    public function __construct(string $id, Module $module, HealthCheckerInterface $healthChecker, array $config = [])
    {
        $this->healthChecker = $healthChecker;
        parent::__construct($id, $module, $config);
    }

    /**
     * Отдаёт состояние сервисов: JSON при ?format=json, иначе HTML-страницу.
     *
     * @return array<string, mixed>|string Данные JSON-ответа либо HTML-разметка
     */
    public function actionIndex()
    {
        $checks = $this->healthChecker->run();
        $healthy = $this->healthChecker->isHealthy($checks);

        Yii::$app->response->statusCode = $healthy ? 200 : 503;

        if (Yii::$app->request->get('format') === 'json') {
            Yii::$app->response->format = Response::FORMAT_JSON;

            return [
                'status' => $healthy ? 'ok' : 'degraded',
                'checks' => $checks,
            ];
        }

        return $this->render('index', [
            'healthy' => $healthy,
            'checks' => $checks,
        ]);
    }
}
