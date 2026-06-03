<?php

declare(strict_types=1);

namespace app\services;

use app\helpers\RequestParamHelper;

/**
 * Собирает модель страницы сообщений из запроса и данных ленты.
 *
 * Сервис инкапсулирует всю прикладную логику страницы: выбор чата по умолчанию,
 * чтение курсоров, построение списка чатов, поиск, постраничную навигацию и
 * вычисление признаков доступности переходов.
 */
class MessagePageService
{
    /**
     * @var int Количество сообщений на странице
     */
    private const PAGE_SIZE = 15;

    /**
     * @var int Количество чатов в боковом списке
     */
    private const CHAT_LIST_SIZE = 30;

    /**
     * @var MessageFeed Сервис чтения чатов и ленты сообщений
     */
    private $feed;

    /**
     * @var RequestParamHelper Помощник для чтения параметров запроса
     */
    private $requestParams;

    /**
     * @param MessageFeed $feed Сервис чтения чатов и ленты сообщений
     * @param RequestParamHelper $requestParams Помощник для чтения параметров запроса
     */
    public function __construct(MessageFeed $feed, RequestParamHelper $requestParams)
    {
        $this->feed = $feed;
        $this->requestParams = $requestParams;
    }

    /**
     * Собирает все данные, необходимые для страницы сообщений.
     *
     * @return MessagePageViewModel Модель представления страницы
     */
    public function build(): MessagePageViewModel
    {
        $chatQueryRaw = $this->requestParams->strParam('chat_q');
        $chatQuery = $chatQueryRaw !== null ? trim($chatQueryRaw) : '';

        $chatCursorTs = $this->requestParams->strParam('ch_ts');
        $chatCursorId = $this->requestParams->intParam('ch_id');
        $chatMode = $this->resolveChatMode();
        $chats = $this->feed->chats(
            self::CHAT_LIST_SIZE,
            $chatCursorTs,
            $chatCursorId,
            $chatMode,
            $chatQuery !== '' ? $chatQuery : null
        );

        $chatsTopTs = null;
        $chatsTopId = null;
        if ($chats !== []) {
            $firstChat = $chats[0];
            $chatsTopTs = is_scalar($firstChat['created_at']) ? (string) $firstChat['created_at'] : '';
            $chatsTopId = is_numeric($firstChat['id']) ? (int) $firstChat['id'] : null;
        }

        $chatsBottomTs = null;
        $chatsBottomId = null;
        if ($chats !== []) {
            $lastChat = $chats[count($chats) - 1];
            $chatsBottomTs = is_scalar($lastChat['created_at']) ? (string) $lastChat['created_at'] : '';
            $chatsBottomId = is_numeric($lastChat['id']) ? (int) $lastChat['id'] : null;
        }

        $chatsCursorTs = $this->requestParams->strParam('ch_ts');
        $chatsCursorId = $this->requestParams->intParam('ch_id');

        $chatId = $this->requestParams->intParam('chat_id');
        if ($chatId === null && $chats !== []) {
            $head = $chats[0]['chat_id'];
            $chatId = is_numeric($head) ? (int) $head : null;
        }

        $queryRaw = $this->requestParams->strParam('q');
        $query = $queryRaw !== null ? trim($queryRaw) : '';
        $isSearch = $query !== '';

        [$mode, $cursorTs, $cursorId] = $this->resolveCursor();

        $messages = [];
        $topTs = null;
        $topId = null;
        $bottomTs = null;
        $bottomId = null;
        $hasNewer = false;
        $hasOlder = false;

        if ($chatId !== null && $isSearch) {
            $messages = $this->feed->search($chatId, $query, self::PAGE_SIZE);
        } elseif ($chatId !== null) {
            $messages = $this->feed->page($chatId, $mode, $cursorTs, $cursorId, self::PAGE_SIZE);

            if ($messages !== []) {
                $first = $messages[0];
                $lastRow = $messages[count($messages) - 1];
                $topTs = is_scalar($first['created_at']) ? (string) $first['created_at'] : '';
                $topId = is_numeric($first['id']) ? (int) $first['id'] : null;
                $bottomTs = is_scalar($lastRow['created_at']) ? (string) $lastRow['created_at'] : '';
                $bottomId = is_numeric($lastRow['id']) ? (int) $lastRow['id'] : null;

                if ($topId !== null) {
                    $hasNewer = $this->feed->hasNewer($chatId, $topTs, $topId);
                }
                if ($bottomId !== null) {
                    $hasOlder = $this->feed->hasOlder($chatId, $bottomTs, $bottomId);
                }
            }
        }

        $chatsNext = null;
        if (count($chats) === self::CHAT_LIST_SIZE) {
            $tail = $chats[count($chats) - 1];
            $chatsNext = [
                'ts' => is_scalar($tail['created_at']) ? (string) $tail['created_at'] : '',
                'id' => is_numeric($tail['id']) ? (int) $tail['id'] : 0,
            ];
        }

        return new MessagePageViewModel(
            $chats,
            $chatId,
            $chatsCursorTs,
            $chatsCursorId,
            $chatQuery,
            $chatsNext,
            $messages,
            $query,
            $isSearch,
            $topTs,
            $topId,
            $bottomTs,
            $bottomId,
            $hasNewer,
            $hasOlder,
            $chatsTopTs,
            $chatsTopId,
            $chatsBottomTs,
            $chatsBottomId,
            $chatMode !== 'first' && (!($chatMode === 'last') || count($chats) === self::CHAT_LIST_SIZE),
            $chatMode !== 'last' && count($chats) === self::CHAT_LIST_SIZE
        );
    }

    /**
     * Определяет режим и курсор ленты по параметрам запроса.
     *
     * @return array{0: string, 1: string|null, 2: int|null} Тройка [режим, created_at-курсор, id-курсор]
     */
    private function resolveCursor(): array
    {
        if ($this->requestParams->strParam('last') !== null) {
            return ['last', null, null];
        }

        $beforeTs = $this->requestParams->strParam('b_ts');
        $beforeId = $this->requestParams->intParam('b_id');
        if ($beforeTs !== null && $beforeId !== null) {
            return ['newer', $beforeTs, $beforeId];
        }

        $afterTs = $this->requestParams->strParam('a_ts');
        $afterId = $this->requestParams->intParam('a_id');
        if ($afterTs !== null && $afterId !== null) {
            return ['older', $afterTs, $afterId];
        }

        return ['first', null, null];
    }

    /**
     * Определяет режим пагинации для списка чатов.
     *
     * @return string Режим: `first`, `older`, `newer` или `last`
     */
    private function resolveChatMode(): string
    {
        $mode = $this->requestParams->strParam('ch_dir');
        if ($mode === 'last') {
            return $mode;
        }

        $cursorTs = $this->requestParams->strParam('ch_ts');
        $cursorId = $this->requestParams->intParam('ch_id');
        if (($mode === 'newer' || $mode === 'older') && $cursorTs !== null && $cursorId !== null) {
            return $mode;
        }

        return 'first';
    }

}
