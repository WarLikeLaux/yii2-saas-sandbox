<?php

declare(strict_types=1);

namespace app\helpers;

use yii\web\Request;

/**
 * Помощник для типизированного чтения параметров запроса.
 *
 * Инкапсулирует извлечение строковых и числовых параметров из `yii\web\Request`,
 * чтобы контроллеры не дублировали одинаковую проверку входных данных.
 */
class RequestParamHelper
{
    /**
     * @var Request Объект HTTP-запроса, из которого читаются параметры
     */
    private $request;

    /**
     * @param Request $request HTTP-запрос приложения
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Возвращает непустой строковый параметр запроса либо `null`.
     *
     * @param string $name Имя параметра запроса
     * @return string|null Значение параметра либо `null`, если оно пустое или не строка
     */
    public function strParam(string $name): ?string
    {
        $value = $this->request->get($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Возвращает числовой параметр запроса как `int` либо `null`.
     *
     * @param string $name Имя параметра запроса
     * @return int|null Значение параметра либо `null`, если оно не является числом
     */
    public function intParam(string $name): ?int
    {
        $value = $this->request->get($name);

        return is_numeric($value) ? (int) $value : null;
    }
}
