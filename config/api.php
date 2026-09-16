<?php

return [

    /*
    |--------------------------------------------------------------------------
    | JSON:API list pagination
    |--------------------------------------------------------------------------
    |
    | page[size] is accepted on every collection endpoint. Values above
    | page_size_max are clamped so a client cannot dump the whole table
    | through offset pagination.
    |
    */

    'page_size_default' => (int) env('API_PAGE_SIZE_DEFAULT', 15),
    'page_size_max' => (int) env('API_PAGE_SIZE_MAX', 100),

    /*
    |--------------------------------------------------------------------------
    | Bulk write limits
    |--------------------------------------------------------------------------
    |
    | One HTTP request, many records. Larger HR / ATTLOG dumps should be
    | split by the client. insert_chunk is the DB write batch size.
    |
    */

    'bulk_max_items' => (int) env('API_BULK_MAX_ITEMS', 500),
    'bulk_insert_chunk' => (int) env('API_BULK_INSERT_CHUNK', 100),

    /*
    |--------------------------------------------------------------------------
    | Spreadsheet import
    |--------------------------------------------------------------------------
    |
    | POST /attendees/import and /attendance-logs/import accept .xlsx / .csv.
    | No extra Composer package is required (ZipArchive + SimpleXML).
    |
    */

    'import_max_rows' => (int) env('API_IMPORT_MAX_ROWS', 2000),
    'import_max_kb' => (int) env('API_IMPORT_MAX_KB', 5120),

    /*
    |--------------------------------------------------------------------------
    | Password reset
    |--------------------------------------------------------------------------
    |
    | The email contains a link to the SPA. Token is also in the query
    | string so a native client can complete POST /api/v1/reset-password.
    |
    */

    'frontend_url' => env('FRONTEND_URL', env('APP_URL', 'http://127.0.0.1:6060')),
    'password_reset_path' => env('API_PASSWORD_RESET_PATH', '/reset-password'),
    'login_path' => env('API_LOGIN_PATH', '/login'),

    /*
    |--------------------------------------------------------------------------
    | Transactional email
    |--------------------------------------------------------------------------
    */

    'mail_brand_color' => env('MAIL_BRAND_COLOR', '#0D9488'),
    'mail_logo_url' => env('MAIL_LOGO_URL'),
    'mail_queue' => env('MAIL_QUEUE', 'notifications'),
];
