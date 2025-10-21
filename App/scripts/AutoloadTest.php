<?php

require __DIR__ . '/../../Vendor/autoload.php'; // Ensure this points to Composer's autoload file
//require 'C:\ampp82\htdocs\api_mylanguage\Vendor/autoload.php';
use App\Services\LoggerService; // Replace with an actual class in your `App` namespace

$logger = new LoggerService(); // Test instantiation
