<?php
return [
    // Comma-separated list from .env; safe default is just 'meta'
    'exclude_keys_default' => array_values(
        array_filter(
            array_map('trim', explode(',', getenv('I18N_EXCLUDE_KEYS_DEFAULT') ?: 'meta'))
        )
    ),
];
