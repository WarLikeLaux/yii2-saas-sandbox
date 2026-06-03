<?php

declare(strict_types=1);

namespace app\services;

/**
 * Неизменяемая модель представления страницы сообщений.
 *
 * Хранит уже собранные данные страницы в одном объекте-значении, чтобы
 * контроллер и view не работали с разрозненным набором параметров.
 */
class MessagePageViewModel
{
    /**
     * @var int|null Идентификатор выбранного чата
     */
    private $chatId;

    /**
     * @var string|null created_at курсора списка чатов
     */
    private $chatsCursorTs;

    /**
     * @var int|null id курсора списка чатов
     */
    private $chatsCursorId;

    /**
     * @var array{ts: string, id: int}|null Курсор следующей страницы чатов
     */
    private $chatsNext;

    /**
     * @var list<array<string, mixed>> Список чатов
     */
    private $chats;

    /**
     * @var string|null created_at нижней границы текущей страницы сообщений
     */
    private $bottomTs;

    /**
     * @var int|null id нижней границы текущей страницы сообщений
     */
    private $bottomId;

    /**
     * @var bool Признак наличия более старых сообщений
     */
    private $hasOlder;

    /**
     * @var bool Признак наличия более новых сообщений
     */
    private $hasNewer;

    /**
     * @var bool Признак режима поиска
     */
    private $isSearch;

    /**
     * @var list<array<string, mixed>> Сообщения страницы или результаты поиска
     */
    private $messages;

    /**
     * @var string Поисковый запрос
     */
    private $query;

    /**
     * @var string|null created_at верхней границы текущей страницы сообщений
     */
    private $topTs;

    /**
     * @var int|null id верхней границы текущей страницы сообщений
     */
    private $topId;

    /**
     * @param list<array<string, mixed>> $chats Список чатов
     * @param int|null $chatId Идентификатор выбранного чата
     * @param string|null $chatsCursorTs created_at курсора списка чатов
     * @param int|null $chatsCursorId id курсора списка чатов
     * @param array{ts: string, id: int}|null $chatsNext Курсор следующей страницы чатов
     * @param list<array<string, mixed>> $messages Сообщения страницы или результаты поиска
     * @param string $query Поисковый запрос
     * @param bool $isSearch Признак режима поиска
     * @param string|null $topTs created_at верхней границы страницы сообщений
     * @param int|null $topId id верхней границы страницы сообщений
     * @param string|null $bottomTs created_at нижней границы страницы сообщений
     * @param int|null $bottomId id нижней границы страницы сообщений
     * @param bool $hasNewer Признак наличия более новых сообщений
     * @param bool $hasOlder Признак наличия более старых сообщений
     */
    public function __construct(
        array $chats,
        ?int $chatId,
        ?string $chatsCursorTs,
        ?int $chatsCursorId,
        ?array $chatsNext,
        array $messages,
        string $query,
        bool $isSearch,
        ?string $topTs,
        ?int $topId,
        ?string $bottomTs,
        ?int $bottomId,
        bool $hasNewer,
        bool $hasOlder
    ) {
        $this->chats = $chats;
        $this->chatId = $chatId;
        $this->chatsCursorTs = $chatsCursorTs;
        $this->chatsCursorId = $chatsCursorId;
        $this->chatsNext = $chatsNext;
        $this->messages = $messages;
        $this->query = $query;
        $this->isSearch = $isSearch;
        $this->topTs = $topTs;
        $this->topId = $topId;
        $this->bottomTs = $bottomTs;
        $this->bottomId = $bottomId;
        $this->hasNewer = $hasNewer;
        $this->hasOlder = $hasOlder;
    }

    /**
     * @return int|null Идентификатор чата либо `null`
     */
    public function getChatId()
    {
        return $this->chatId;
    }

    /**
     * @return string|null created_at курсора списка чатов либо `null`
     */
    public function getChatsCursorTs()
    {
        return $this->chatsCursorTs;
    }

    /**
     * @return int|null id курсора списка чатов либо `null`
     */
    public function getChatsCursorId()
    {
        return $this->chatsCursorId;
    }

    /**
     * @return array{ts: string, id: int}|null Курсор следующей страницы чатов либо `null`
     */
    public function getChatsNext()
    {
        return $this->chatsNext;
    }

    /**
     * @return list<array<string, mixed>> Список чатов
     */
    public function getChats(): array
    {
        return $this->chats;
    }

    /**
     * @return string|null created_at нижней границы страницы сообщений либо `null`
     */
    public function getBottomTs()
    {
        return $this->bottomTs;
    }

    /**
     * @return int|null id нижней границы страницы сообщений либо `null`
     */
    public function getBottomId()
    {
        return $this->bottomId;
    }

    /**
     * @return bool true, если есть более старые сообщения
     */
    public function hasOlder(): bool
    {
        return $this->hasOlder;
    }

    /**
     * @return bool true, если есть более новые сообщения
     */
    public function hasNewer(): bool
    {
        return $this->hasNewer;
    }

    /**
     * @return bool true, если выполняется поиск
     */
    public function isSearch(): bool
    {
        return $this->isSearch;
    }

    /**
     * @return list<array<string, mixed>> Сообщения страницы или результаты поиска
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * @return string Поисковый запрос
     */
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * @return string|null created_at верхней границы страницы сообщений либо `null`
     */
    public function getTopTs()
    {
        return $this->topTs;
    }

    /**
     * @return int|null id верхней границы страницы сообщений либо `null`
     */
    public function getTopId()
    {
        return $this->topId;
    }
}
