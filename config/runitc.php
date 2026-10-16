<?php

return [
    'legacy_base_path' => base_path(),
    'legacy_modules_path' => base_path('modules'),
    'legacy_assets_path' => base_path('assets'),
    'timezone' => 'Asia/Jakarta',
    'filing_use_new_tables' => env('FILING_USE_NEW_TABLES', false),
    'filing_local_storage_path' => env('FILING_LOCAL_STORAGE_PATH', ''),
    'filing_http_upload_enabled' => env('FILING_HTTP_UPLOAD_ENABLED', false),
    'filing_http_upload_url' => env('FILING_HTTP_UPLOAD_URL', ''),
    'filing_http_upload_token' => env('FILING_HTTP_UPLOAD_TOKEN', ''),
    'filing_http_upload_timeout' => env('FILING_HTTP_UPLOAD_TIMEOUT', 30),
];
