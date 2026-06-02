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
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        'declare_strict_types' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'trailing_comma_in_multiline' => true,
        'single_quote' => true,
        'no_trailing_whitespace' => true,
        'blank_line_after_opening_tag' => true,
        'binary_operator_spaces' => true,
        'concat_space' => ['spacing' => 'one'],
        'cast_spaces' => ['space' => 'single'],
        'unary_operator_spaces' => true,
        'no_extra_blank_lines' => true,
        'whitespace_after_comma_in_array' => true,
    ])
    ->setFinder($finder);
