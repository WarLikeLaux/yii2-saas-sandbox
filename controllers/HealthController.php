<?php

namespace app\controllers;

use app\components\HealthChecker;
use Yii;
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
    public function actionIndex()
    {
        $checker = new HealthChecker();
        $checks = $checker->run();
        $healthy = $checker->isHealthy($checks);

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
