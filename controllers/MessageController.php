<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\MessagePageService;
use yii\base\Module;
use yii\web\Controller;

/**
 * Контроллер страницы чатов и сообщений.
 *
 * Вся прикладная логика сборки страницы вынесена в `MessagePageService`.
 * Контроллер только вызывает сервис и передаёт результат во view.
 */
class MessageController extends Controller
{
    /**
     * @var MessagePageService Сервис сборки модели страницы сообщений
     */
    private $pageService;

    /**
     * @param string $id Идентификатор контроллера
     * @param Module $module Модуль, которому принадлежит контроллер
     * @param MessagePageService $pageService Сервис сборки модели страницы
     * @param array<string, mixed> $config Дополнительная конфигурация
     */
    public function __construct(string $id, Module $module, MessagePageService $pageService, array $config = [])
    {
        $this->pageService = $pageService;
        parent::__construct($id, $module, $config);
    }

    /**
     * Отдаёт страницу чата: список чатов, ленту/результаты поиска и навигацию.
     *
     * @return string HTML-разметка страницы
     */
    public function actionIndex(): string
    {
        return $this->render('index', [
            'page' => $this->pageService->build(),
        ]);
    }
}
