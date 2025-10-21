<?php

// Detect environment based on server name
$environment = (in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1'])) ? 'local' : 'remote';

// Load environment-specific configuration
$configFile = __DIR__ . ($environment === 'local' ? 'private/.env.local.php' : 'private/.env.remote.php');
require_once $configFile;
