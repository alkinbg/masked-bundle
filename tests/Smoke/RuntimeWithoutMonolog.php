<?php

declare(strict_types=1);

use Masked\MaskedBundle;
use Masked\SensitiveDataMasker;
use Symfony\Component\DependencyInjection\ContainerBuilder;

require dirname(__DIR__, 2).'/vendor/autoload.php';

if (class_exists(Monolog\LogRecord::class)) {
    throw new RuntimeException('Monolog must not be installed in the minimal runtime test.');
}

$masked = new SensitiveDataMasker()->mask(
    'Card: 4111111111111111',
);

$expected = 'Card: '.str_repeat('█', 16);

if ($expected !== $masked) {
    throw new RuntimeException('SensitiveDataMasker failed in the minimal runtime environment.');
}

$container = new ContainerBuilder();

$extension = new MaskedBundle()->getContainerExtension();

if (null === $extension) {
    throw new RuntimeException('MaskedBundle did not provide a container extension.');
}

$extension->load([], $container);
$container->compile();
