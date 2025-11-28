<?php

require_once __DIR__ . '/../vendor/autoload.php';  // lowercase "vendor" is correct

use ScriptsTemporary\ImportToTranslationMemory;
use App\Configuration\Config;


$script = new ImportToTranslationMemory('DBS2.tsv');
$recordsAdded = $script->run();

echo json_encode(['status' => 'processed '. $recordsAdded]);
