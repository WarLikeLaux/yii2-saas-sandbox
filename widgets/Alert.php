<?php

declare(strict_types=1);

namespace app\widgets;

use Yii;
use yii\web\Application;

/**
 * Виджет рендерит flash-сообщения сессии в виде бутстраповских алертов.
 *
 * Перебирает все типы flash-сообщений (error, danger, success, info, warning),
 * для каждого устанавливленного сообщения выводит соответствующий
 * Bootstrap-алерт и после вывода удаляет сообщение из сессии. Сообщения
 * показываются в порядке типов, заданном в свойстве $alertTypes.
 *
 * Установить сообщение можно так:
 *
 * ```php
 * Yii::$app->session->setFlash('error', 'Текст сообщения');
 * Yii::$app->session->setFlash('success', 'Текст сообщения');
 * Yii::$app->session->setFlash('info', 'Текст сообщения');
 * ```
 *
 * Несколько сообщений одного типа задаются массивом:
 *
 * ```php
 * Yii::$app->session->setFlash('error', ['Ошибка 1', 'Ошибка 2']);
 * ```
 */
class Alert extends \yii\bootstrap5\Widget
{
    /**
     * @var array<string, string> Соответствие типов flash-сообщений CSS-классам алертов.
     * Массив задаётся как $key => $value, где:
     * - key: имя переменной flash-сообщения в сессии;
     * - value: CSS-класс Bootstrap-алерта (danger, success, info, warning).
     */
    public $alertTypes = [
        'error' => 'alert-danger',
        'danger' => 'alert-danger',
        'success' => 'alert-success',
        'info' => 'alert-info',
        'warning' => 'alert-warning',
    ];
    /**
     * @var array<string, mixed> Опции рендеринга кнопки закрытия алерта.
     * Массив передаётся в \yii\bootstrap5\Alert::closeButton.
     */
    public $closeButton = [];

    /**
     * Выводит все flash-сообщения сессии бутстраповскими алертами и очищает их.
     *
     * @return void
     */
    public function run()
    {
        $app = Yii::$app;
        assert($app instanceof Application);
        $session = $app->getSession();

        $appendClass = isset($this->options['class']) ? ' ' . $this->options['class'] : '';

        foreach (array_keys($this->alertTypes) as $type) {
            $flash = $session->getFlash($type);

            foreach ((array) $flash as $i => $message) {
                echo \yii\bootstrap5\Alert::widget([
                    'body' => $message,
                    'closeButton' => $this->closeButton,
                    'options' => array_merge($this->options, [
                        'id' => $this->getId() . '-' . $type . '-' . $i,
                        'class' => $this->alertTypes[$type] . $appendClass,
                    ]),
                ]);
            }

            $session->removeFlash($type);
        }
    }
}
