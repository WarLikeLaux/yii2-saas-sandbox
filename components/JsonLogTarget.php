<?php

declare(strict_types=1);

namespace app\components;

use yii\helpers\VarDumper;
use yii\log\FileTarget;
use yii\log\Logger;

/**
 * Лог-таргет, пишущий каждое сообщение одной строкой JSON.
 *
 * Машиночитаемые логи — основа эксплуатации: их собирает агент (filebeat) и
 * отправляет в хранилище (Elasticsearch), а смотрят через Kibana. Текстовый
 * формат для этого неудобен — парсить регулярками хрупко. Здесь каждое событие
 * сериализуется в JSON с полями `ts`, `level`, `category`, `correlation_id`,
 * `message`, поэтому по логам можно фильтровать и собирать цепочку обработки по
 * `correlation_id` ({@see CorrelationContext}).
 */
class JsonLogTarget extends FileTarget
{
    /**
     * @var callable|null Резолвер идентификатора корреляции (возвращает строку)
     */
    public $correlationIdResolver;

    /**
     * Форматирует одно сообщение лога в строку JSON.
     *
     * @param array<int, mixed>|mixed $message Сообщение лога Yii: `[текст, уровень, категория, время, ...]`
     * @return string Строка JSON
     */
    public function formatMessage($message)
    {
        return $this->toJson(is_array($message) ? $message : []);
    }

    /**
     * Собирает строку JSON из массива-сообщения лога Yii.
     *
     * @param array<int, mixed> $message Сообщение лога: `[текст, уровень, категория, время, ...]`
     * @return string Строка JSON (или `{}` при ошибке сериализации)
     */
    public function toJson(array $message): string
    {
        $text = $message[0] ?? '';
        $level = isset($message[1]) && is_int($message[1]) ? $message[1] : Logger::LEVEL_INFO;
        $category = isset($message[2]) && is_string($message[2]) ? $message[2] : 'application';
        $timestamp = isset($message[3]) && (is_int($message[3]) || is_float($message[3])) ? (int) $message[3] : 0;

        $correlationId = '';
        if (is_callable($this->correlationIdResolver)) {
            $resolved = call_user_func($this->correlationIdResolver);
            $correlationId = is_string($resolved) ? $resolved : '';
        }

        $json = json_encode([
            'ts' => date('c', $timestamp),
            'level' => Logger::getLevelName($level),
            'category' => $category,
            'correlation_id' => $correlationId,
            'message' => is_string($text) ? $text : VarDumper::export($text),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? '{}' : $json;
    }
}
