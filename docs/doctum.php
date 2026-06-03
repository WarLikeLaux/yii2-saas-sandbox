<?php

declare(strict_types=1);

use Doctum\Doctum;
use Doctum\RemoteRepository\GitHubRemoteRepository;
use Symfony\Component\Finder\Finder;

$root = dirname(__DIR__);

$iterator = Finder::create()
    ->files()
    ->name('*.php')
    ->exclude('vendor')
    ->exclude('runtime')
    ->exclude('tests')
    ->exclude('web')
    ->exclude('docs')
    ->exclude('config')
    ->in($root);

return new Doctum($iterator, [
    'title' => 'SaaS Sandbox API',
    'language' => 'en',
    'build_dir' => $root . '/docs/api',
    'cache_dir' => $root . '/docs/.doctum-cache',
    'source_dir' => $root . '/',
    'default_opened_level' => 2,
    'remote_repository' => new GitHubRemoteRepository('WarLikeLaux/yii2-saas-sandbox', $root),
]);
