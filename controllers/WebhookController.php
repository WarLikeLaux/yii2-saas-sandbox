<?php

declare(strict_types=1);

namespace app\controllers;

use app\jobs\DemoJob;
use app\services\QueueService;
use app\services\WebhookService;
use yii\base\Module;
use yii\web\Controller;
use yii\web\Response;

/**
 * Приём входящих вебхуков от внешних площадок.
 *
 * Точка входа устроена по принципу «быстрый ACK»: контроллер только проверяет
 * подпись, отсеивает повторы и кладёт тяжёлую обработку в очередь, после чего
 * сразу отвечает площадке. Сама обработка идёт фоновым воркером (см. уроки по
 * очередям), чтобы не держать HTTP-соединение и успеть ответить до таймаута
 * площадки.
 */
class WebhookController extends Controller
{
    /**
     * @var bool Вебхуки приходят без CSRF-токена — проверку отключаем
     */
    public $enableCsrfValidation = false;

    /**
     * @var WebhookService Сервис приёма вебхуков (подпись и отсев повторов)
     */
    private $webhookService;

    /**
     * @var QueueService Сервис постановки фоновых задач в очередь
     */
    private $queueService;

    /**
     * @param string $id Идентификатор контроллера
     * @param Module $module Модуль, которому принадлежит контроллер
     * @param WebhookService $webhookService Сервис приёма вебхуков (внедряется контейнером)
     * @param QueueService $queueService Сервис постановки задач (внедряется контейнером)
     * @param array<string, mixed> $config Дополнительная конфигурация
     */
    public function __construct(
        string $id,
        Module $module,
        WebhookService $webhookService,
        QueueService $queueService,
        array $config = []
    ) {
        $this->webhookService = $webhookService;
        $this->queueService = $queueService;
        parent::__construct($id, $module, $config);
    }

    /**
     * Принимает вебхук: проверяет подпись, отсеивает повтор, ставит обработку в
     * очередь и сразу отвечает.
     *
     * @return array<string, string> Тело JSON-ответа (статус приёма)
     */
    public function actionReceive(): array
    {
        $this->response->format = Response::FORMAT_JSON;

        $rawBody = $this->request->getRawBody();
        $signature = (string) $this->request->headers->get('X-Signature', '');

        if (!$this->webhookService->verifySignature($rawBody, $signature)) {
            $this->response->statusCode = 401;

            return ['status' => 'invalid_signature'];
        }

        $data = json_decode($rawBody, true);
        $eventId = '';
        if (is_array($data) && isset($data['id'])) {
            $id = $data['id'];
            if (is_string($id) || is_int($id)) {
                $eventId = (string) $id;
            }
        }
        if ($eventId === '') {
            $this->response->statusCode = 400;

            return ['status' => 'missing_event_id'];
        }

        if (!$this->webhookService->registerOnce($eventId)) {
            return ['status' => 'duplicate'];
        }

        $this->queueService->push(new DemoJob(['message' => 'webhook ' . $eventId]));
        $this->response->statusCode = 202;

        return ['status' => 'accepted'];
    }
}
