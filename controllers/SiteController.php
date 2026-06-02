<?php

declare(strict_types=1);

namespace app\controllers;

use yii\web\Controller;
use yii\web\ErrorAction;

/**
 * Публичная часть сайта песочницы.
 *
 * Содержит только главную страницу и обработчик ошибок. Авторизация,
 * регистрация и демонстрационные страницы шаблона Yii2 удалены — песочница
 * сфокусирована на инфраструктуре (см. /health) и фоновых задачах.
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
     * Отдаёт главную страницу песочницы.
     *
     * @return string HTML-разметка главной страницы
     */
    public function actionIndex(): string
    {
        return $this->render('index');
    }
}
