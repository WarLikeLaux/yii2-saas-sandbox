<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/commands',
        __DIR__ . '/components',
        __DIR__ . '/controllers',
        __DIR__ . '/jobs',
        __DIR__ . '/models',
    ])
    ->withSkip([
        __DIR__ . '/vendor',
        __DIR__ . '/runtime',
        __DIR__ . '/tests/_output',
    ])
    ->withPhpVersion(PhpVersion::PHP_74)
    ->withSets([
        SetList::DEAD_CODE,
        SetList::CODE_QUALITY,
    ]);
