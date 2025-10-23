<?php
// bootstrap.php

// Load Composer autoloader
require_once __DIR__ . '/Vendor/autoload.php';

// Optional: Load config, env, debug tools
require_once __DIR__ . '/App/Services/Debugging.php';

// Set error reporting (optional for CLI)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load environment constants or configuration
use App\Configuration\Config;
Config::initialize(); 
