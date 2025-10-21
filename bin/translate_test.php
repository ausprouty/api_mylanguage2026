<?php
declare(strict_types=1);

use DI\ContainerBuilder;

require __DIR__ . '/../vendor/autoload.php';

if ($argc < 4) {
    fwrite(STDERR,
        "Usage: php translate_test.php <type> <subject> <lang> [variant]\n"
    );
    exit(1);
}

[$script, $type, $subject, $lang] = $argv;
$variant = $argv[4] ?? null;

$builder = new ContainerBuilder();
$builder->addDefinitions(__DIR__ . '/../Configuration/di-all.php');
$container = $builder->build();

$textResolver = $container->get(
    \App\Services\TextBundleResolver::class
);

$bundle = $textResolver->resolve([
    'type'    => $type,       // e.g. "commonContent" or "interface"
    'subject' => $subject,    // e.g. "hope" or "app"
    'variant' => $variant,    // e.g. "wsu" or null
    'lang'    => $lang,       // e.g. "gjr00"
]);

echo json_encode(
    ['status' => 'ok', 'data' => $bundle],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
) . PHP_EOL;
