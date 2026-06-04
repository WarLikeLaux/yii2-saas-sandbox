<?php

declare(strict_types=1);

namespace app\assets;

use yii\bootstrap5\BootstrapIconAsset;
use yii\web\AssetBundle;

/**
 * Основной набор ресурсов (CSS/JS) приложения.
 *
 * Регистрируется в макете `views/layouts/main.php` и через `$depends` тянет
 * зависимости: Bootstrap 5, иконки Bootstrap Icons и базовый набор Yii.
 * Собственные стили лежат в `web/css/site.css`.
 */
class AppAsset extends AssetBundle
{
    /**
     * @var string Корень публикуемых ресурсов; `@webroot` — каталог `web/`
     */
    public $basePath = '@webroot';

    /**
     * @var string Базовый URL ресурсов; `@web` — корневой URL приложения
     */
    public $baseUrl = '@web';

    /**
     * @var list<string> Собственные CSS-файлы (относительно `$baseUrl`)
     */
    public $css = [
        'css/site.css',
    ];

    /**
     * @var list<string> Собственные JS-файлы (пусто: весь JS приходит из `$depends`)
     */
    public $js = [
    ];

    /**
     * @var list<class-string|string> Бандлы-зависимости: Bootstrap Icons, ядро Yii и Bootstrap 5
     */
    public $depends = [
        BootstrapIconAsset::class,
        'yii\web\YiiAsset',
        'yii\bootstrap5\BootstrapAsset',
    ];
}
