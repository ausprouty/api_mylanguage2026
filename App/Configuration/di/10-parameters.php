<?php
declare(strict_types=1);

use App\Configuration\Config;

return [
    // Core
    'base_dir' => fn () => Config::get('base_dir'),
    'base_url' => fn () => Config::get('base_url', ''),

    // Paths (note the nested 'resources.templates')
    'storage.path'    => fn () => Config::getDir('storage', '/storage'),
    'templates.root'  => fn () => Config::getDir('resources.templates', 'Resources/templates/'),
    'logs.path'       => fn () => Config::getDir('logs', 'logs/'),
    'imports.path'    => fn () => Config::getDir('imports', 'imports/data/'),
    // something for logging errors:
     'log_mirror_error_log' => true,


    // I18n
    
    'i18n.baseLanguage'     => fn () => Config::get('i18n.baseLanguage', 'eng00'),
    'i18n.autoMt.enabled' => true,
     // If you want to maintain a list, put it here; otherwise leave [] to rely on googleCode presence.
    'i18n.autoMt.allowGoogle'  => [], // e.g. ['gu','mr','ur','hi', ...]

    // Endpoints / URLs
    // Your file defines 'api.*' keys, not 'endpoints.*' or 'urls.*'.
    // Provide sensible defaults until you add them to config.
    'endpoint.biblebrain'   => fn () => Config::get('endpoints.biblebrain', 'https://4.dbt.io'),
    'endpoint.biblegateway' => fn () => Config::get('endpoints.biblegateway', 'https://www.biblegateway.com'),
    'endpoint.wordproject'    => fn () => Config::get('endpoints.wordproject', 'https://example.com/api'),
    'endpoint.youversion'   => fn () => Config::get('endpoints.youversion', 'https://api.youversion.com'),
    'endpoints.cloudfront' => fn () => Config::get('endpoints.cloudfront', 'https://d0000000000000.cloudfront.net'),
    // Not present in your config; fall back or leave blank
    'url.website'           => fn () => Config::get('urls.website', Config::get('base_url', '')),
];
