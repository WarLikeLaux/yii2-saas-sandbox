<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\HealthServiceInterface;
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
     * @var HealthServiceInterface Сервис проверки состояния инфраструктуры
     */
    private $healthService;

    /**
     * @param string $id Идентификатор контроллера
     * @param Module $module Модуль, которому принадлежит контроллер
     * @param HealthServiceInterface $healthService Сервис проверки (внедряется контейнером)
     * @param array<string, mixed> $config Дополнительная конфигурация
     */
    public function __construct(string $id, Module $module, HealthServiceInterface $healthService, array $config = [])
    {
        $this->healthService = $healthService;
        parent::__construct($id, $module, $config);
    }

    /**
     * Отдаёт состояние сервисов: JSON при ?format=json, иначе HTML-страницу.
     *
     * @return array<string, mixed>|string Данные JSON-ответа либо HTML-разметка
     */
    public function actionIndex()
    {
        $report = $this->healthService->report();

        $this->response->statusCode = $report->isHealthy() ? 200 : 503;

        if ($this->request->get('format') === 'json') {
            $this->response->format = Response::FORMAT_JSON;

            return [
                'status' => $report->getStatus(),
                'checks' => $report->getChecks(),
            ];
        }

        return $this->render('index', [
            'healthy' => $report->isHealthy(),
            'checks' => $report->getChecks(),
        ]);
    }
}
