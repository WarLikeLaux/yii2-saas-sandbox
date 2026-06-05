<?php

declare(strict_types=1);

namespace tests\unit\components;

use app\components\CorrelationContext;

/**
 * Тесты контекста корреляции.
 *
 * Проверяется хранение идентификатора и генерация при отсутствии.
 */
class CorrelationContextTest extends \Codeception\Test\Unit
{
    /**
     * Установленный идентификатор возвращается без изменений.
     */
    public function testSetGet(): void
    {
        $context = new CorrelationContext();
        $context->set('req-123');

        verify($context->get())->equals('req-123');
    }

    /**
     * Если идентификатор не задан, ensure() генерирует непустой.
     */
    public function testEnsureGeneratesWhenEmpty(): void
    {
        $context = new CorrelationContext();

        $id = $context->ensure();

        verify($id)->notEmpty();
        verify($context->get())->equals($id);
    }

    /**
     * Если идентификатор уже задан, ensure() его не меняет.
     */
    public function testEnsureKeepsExisting(): void
    {
        $context = new CorrelationContext();
        $context->set('keep-me');

        verify($context->ensure())->equals('keep-me');
    }
}
