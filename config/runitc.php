<?php

return [
    'legacy_base_path' => base_path(),
    'legacy_modules_path' => base_path('modules'),
    'legacy_assets_path' => base_path('assets'),
    'timezone' => 'Asia/Jakarta',
    'filing_use_new_tables' => env('FILING_USE_NEW_TABLES', false),
];
