<?php

// cors.php

// Allow from any origin
header('Access-Control-Allow-Origin: *');

// Allow specific HTTP methods
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

// Allow specific HTTP headers
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Respond to preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

