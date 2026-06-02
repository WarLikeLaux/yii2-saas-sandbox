<?php

/** @var yii\web\View $this */
/** @var string $name */
/** @var string $message */
/** @var Exception $exception */

use yii\bootstrap5\Html;

$this->title = $name;
?>
<div class="site-error py-5 my-3">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card border-0 shadow-sm rounded-3 text-center">
                <div class="card-body p-4 p-md-5">
                    <div class="display-4 text-danger mb-3">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <h1 class="h3 fw-semibold mb-3"><?= Html::encode($name) ?></h1>

                    <div class="alert alert-danger text-start" role="alert">
                        <?= nl2br(Html::encode($message)) ?>
                    </div>

                    <p class="text-secondary mb-4">
                        Ошибка возникла во время обработки запроса сервером.
                        Если вы считаете это сбоем сервера — свяжитесь с нами.
                    </p>

                    <?= Html::a(
                        '<i class="bi bi-house-door me-1"></i>На главную',
                        Yii::$app->homeUrl,
                        ['class' => 'btn btn-primary px-4', 'encode' => false]
                    ) ?>
                </div>
            </div>
        </div>
    </div>
</div>
