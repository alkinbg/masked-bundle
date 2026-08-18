<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return new Config()
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        'declare_strict_types' => true,
        'phpdoc_to_comment' => false,
    ])
    ->setFinder(
        Finder::create()
            ->in([
                __DIR__.'/config',
                __DIR__.'/src',
                __DIR__.'/tests',
            ])
            ->name('*.php'),
    );
