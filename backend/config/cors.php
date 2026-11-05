<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | SRS §6.2: Web chỉ gọi Laravel qua cùng Nginx trong triển khai mặc định;
    | cấu hình này cho phép mở chính xác origin frontend production thay vì
    | mở rộng '*' hoặc không có header CORS nào.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter([
        env('CORS_ALLOWED_ORIGIN', env('APP_URL')),
        'http://localhost:8080',
        'http://localhost:5173',
    ])),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['X-Report-Hash', 'X-Request-Id'],

    'max_age' => 0,

    'supports_credentials' => false,

];
