<?php

declare(strict_types=1);

namespace tests\unit\components;

use app\components\JsonLogTarget;
use yii\log\Logger;

/**
 * Тесты JSON-лог-таргета.
 *
 * Проверяется, что сообщение лога сериализуется в JSON с нужными полями и
 * подставленным идентификатором корреляции.
 */
class JsonLogTargetTest extends \Codeception\Test\Unit
{
    /**
     * Сообщение сериализуется в JSON с полями и correlation id.
     */
    public function testToJsonContainsFieldsAndCorrelationId(): void
    {
        $target = new JsonLogTarget();
        $target->correlationIdResolver = static function (): string {
            return 'cid-1';
        };

        $json = $target->toJson(['привет', Logger::LEVEL_INFO, 'demo', 1700000000]);
        $decoded = json_decode($json, true);

        verify(is_array($decoded))->true();
        verify($decoded['level'])->equals('info');
        verify($decoded['category'])->equals('demo');
        verify($decoded['correlation_id'])->equals('cid-1');
        verify($decoded['message'])->equals('привет');
    }

    /**
     * Без резолвера correlation id пустой, но JSON корректный.
     */
    public function testToJsonWithoutResolver(): void
    {
        $target = new JsonLogTarget();

        $json = $target->toJson(['x', Logger::LEVEL_ERROR, 'app', 1700000000]);
        $decoded = json_decode($json, true);

        verify(is_array($decoded))->true();
        verify($decoded['correlation_id'])->equals('');
        verify($decoded['level'])->equals('error');
    }
}
