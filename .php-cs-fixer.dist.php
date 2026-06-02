<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/commands',
        __DIR__ . '/components',
        __DIR__ . '/config',
        __DIR__ . '/controllers',
        __DIR__ . '/jobs',
        __DIR__ . '/models',
        __DIR__ . '/widgets',
        __DIR__ . '/tests/unit',
    ]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'trailing_comma_in_multiline' => true,
        'single_quote' => true,
        'no_trailing_whitespace' => true,
        'blank_line_after_opening_tag' => true,
    ])
    ->setFinder($finder);
