<?php

declare(strict_types=1);

namespace tests\unit\helpers;

use app\helpers\DbHelper;

/**
 * Тесты помощника низкоуровневых операций с SQL и базой данных.
 */
class DbHelperTest extends \Codeception\Test\Unit
{
    /**
     * escapeLike() оставляет обычный текст без изменений.
     */
    public function testEscapeLikeLeavesPlainTextUntouched(): void
    {
        verify((new DbHelper())->escapeLike('message text'))->equals('message text');
    }

    /**
     * escapeLike() экранирует символ процента, который в `LIKE` означает любую
     * последовательность символов.
     */
    public function testEscapeLikePercent(): void
    {
        verify((new DbHelper())->escapeLike('100%'))->equals('100\\%');
    }

    /**
     * escapeLike() экранирует обратный слеш до обработки остальных спецсимволов.
     */
    public function testEscapeLikeSlash(): void
    {
        verify((new DbHelper())->escapeLike('path\\name'))->equals('path\\\\name');
    }

    /**
     * escapeLike() экранирует подчёркивание, которое в `LIKE` означает один символ.
     */
    public function testEscapeLikeUnderscore(): void
    {
        verify((new DbHelper())->escapeLike('user_name'))->equals('user\\_name');
    }
}
