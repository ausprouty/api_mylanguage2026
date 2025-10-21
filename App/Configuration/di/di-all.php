<?php

$parts = [
    __DIR__ . '/10-parameters.php',
    __DIR__ . '/20-contracts.php',
    __DIR__ . '/25-http.php',
    __DIR__ . '/25-web.php',
    __DIR__ . '/30-services.php',
    __DIR__ . '/32-translation.php',
    __DIR__ . '/35-factories.php',
    __DIR__ . '/40-repositories.php',
    __DIR__ . '/50-controllers.php',
    __DIR__ . '/55-middleware.php',
    __DIR__ . '/60-i18n.php',

];

// choose env tail
$envTail = is_file(__DIR__ . '/dev.php') ? '/dev.php' : '/prod.php';
$parts[] = __DIR__ . $envTail;

$config = [];
foreach ($parts as $file) {
    if (is_file($file)) {
        $config = array_replace($config, require $file);
    }
}
return $config;
