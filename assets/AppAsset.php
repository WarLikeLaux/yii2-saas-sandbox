<?php

namespace app\assets;

use yii\bootstrap5\BootstrapIconAsset;
use yii\web\AssetBundle;

/**
 * Основной набор ресурсов приложения.
 */
class AppAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'css/site.css',
    ];
    public $js = [
    ];
    public $depends = [
        BootstrapIconAsset::class,
        'yii\web\YiiAsset',
        'yii\bootstrap5\BootstrapAsset',
    ];
}
