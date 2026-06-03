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
     * @var string Поисковый запрос по чатам
     */
    private $chatQuery;

    /**
     * @var array{ts: string, id: int}|null Курсор следующей страницы чатов
     */
    private $chatsNext;

    /**
     * @var list<array<string, mixed>> Список чатов
     */
    private $chats;

    /**
     * @var string|null created_at верхней границы текущей страницы чатов
     */
    private $chatsTopTs;

    /**
     * @var int|null id верхней границы текущей страницы чатов
     */
    private $chatsTopId;

    /**
     * @var string|null created_at нижней границы текущей страницы чатов
     */
    private $chatsBottomTs;

    /**
     * @var int|null id нижней границы текущей страницы чатов
     */
    private $chatsBottomId;

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
     * @var bool Признак наличия более старых чатов
     */
    private $chatsOlder;

    /**
     * @var bool Признак наличия более новых чатов
     */
    private $chatsNewer;

    /**
     * @param list<array<string, mixed>> $chats Список чатов
     * @param int|null $chatId Идентификатор выбранного чата
     * @param string|null $chatsCursorTs created_at курсора списка чатов
     * @param int|null $chatsCursorId id курсора списка чатов
     * @param string $chatQuery Поисковый запрос по чатам
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
     * @param string|null $chatsTopTs created_at верхней границы страницы чатов
     * @param int|null $chatsTopId id верхней границы страницы чатов
     * @param string|null $chatsBottomTs created_at нижней границы страницы чатов
     * @param int|null $chatsBottomId id нижней границы страницы чатов
     * @param bool $chatsNewer Признак наличия более новых чатов
     * @param bool $chatsOlder Признак наличия более старых чатов
     */
    public function __construct(
        array $chats,
        ?int $chatId,
        ?string $chatsCursorTs,
        ?int $chatsCursorId,
        string $chatQuery,
        ?array $chatsNext,
        array $messages,
        string $query,
        bool $isSearch,
        ?string $topTs,
        ?int $topId,
        ?string $bottomTs,
        ?int $bottomId,
        bool $hasNewer,
        bool $hasOlder,
        ?string $chatsTopTs,
        ?int $chatsTopId,
        ?string $chatsBottomTs,
        ?int $chatsBottomId,
        bool $chatsNewer,
        bool $chatsOlder
    ) {
        $this->chats = $chats;
        $this->chatId = $chatId;
        $this->chatsCursorTs = $chatsCursorTs;
        $this->chatsCursorId = $chatsCursorId;
        $this->chatQuery = $chatQuery;
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
        $this->chatsTopTs = $chatsTopTs;
        $this->chatsTopId = $chatsTopId;
        $this->chatsBottomTs = $chatsBottomTs;
        $this->chatsBottomId = $chatsBottomId;
        $this->chatsNewer = $chatsNewer;
        $this->chatsOlder = $chatsOlder;
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
     * @return string Поисковый запрос по чатам
     */
    public function getChatQuery(): string
    {
        return $this->chatQuery;
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
     * @return string|null created_at верхней границы страницы чатов либо `null`
     */
    public function getChatsTopTs()
    {
        return $this->chatsTopTs;
    }

    /**
     * @return int|null id верхней границы страницы чатов либо `null`
     */
    public function getChatsTopId()
    {
        return $this->chatsTopId;
    }

    /**
     * @return string|null created_at нижней границы страницы чатов либо `null`
     */
    public function getChatsBottomTs()
    {
        return $this->chatsBottomTs;
    }

    /**
     * @return int|null id нижней границы страницы чатов либо `null`
     */
    public function getChatsBottomId()
    {
        return $this->chatsBottomId;
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
     * @return bool true, если есть более новые чаты
     */
    public function hasChatsNewer(): bool
    {
        return $this->chatsNewer;
    }

    /**
     * @return bool true, если есть более старые чаты
     */
    public function hasChatsOlder(): bool
    {
        return $this->chatsOlder;
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
