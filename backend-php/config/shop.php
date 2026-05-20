<?php

return [
    'jwt_secret' => env('JWT_SECRET', 'your_jwt_secret_key_here_change_in_production'),
    'jwt_algorithm' => env('JWT_ALGORITHM', 'HS256'),
    'jwt_expiration_seconds' => (int) env('JWT_EXPIRATION_SECONDS', 2592000),
    'jwt_refresh_expiration_seconds' => (int) env('JWT_REFRESH_EXPIRATION_SECONDS', 7776000),
    'file_storage_location' => env('FILE_STORAGE_LOCATION', 'images'),
    'file_max_size_mb' => (int) env('FILE_MAX_SIZE_MB', 10),
    'default_low_stock_threshold' => (int) env('DEFAULT_LOW_STOCK_THRESHOLD', 10),
    'server_port' => (int) env('SERVER_PORT', 8080),
    'vat_rate' => '0.1',
    'shipping_threshold' => '10000000',
    'shipping_fee' => '30000',
];
