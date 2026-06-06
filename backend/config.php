<?php

declare(strict_types=1);

// Detect if running locally or in production
$isLocal = strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;

$dbConfig = [
    'host' => 'localhost',
    'port' => 3306,
    'name' => 'mth_global',
    'user' => 'root',
    'pass' => '',
];

/*
$dbConfig = $isLocal ? [
    'host' => 'localhost',
    'port' => 3306,
    'name' => 'nu_farm',
    'user' => 'root',
    'pass' => '',
] : [
    'host' => 'sql5.freesqldatabase.com',
    'port' => 3306,
    'name' => 'sql5825645',
    'user' => 'sql5825645',
    'pass' => 'RrpsLHiTJr',
];
*/

$config = [
    'db' => [
        'host' => getenv('DB_HOST') ?: $dbConfig['host'],
        'port' => (int) (getenv('DB_PORT') ?: $dbConfig['port']),
        'name' => getenv('DB_NAME') ?: $dbConfig['name'],
        'user' => getenv('DB_USER') ?: $dbConfig['user'],
        'pass' => getenv('DB_PASS') ?: $dbConfig['pass'],
        'charset' => 'utf8mb4',
    ],
    'smtp' => [
        'host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
        'port' => (int) (getenv('SMTP_PORT') ?: 465),
        'user' => getenv('SMTP_USER') ?: '',
        'pass' => getenv('SMTP_PASS') ?: '',
        'from_email' => getenv('SMTP_FROM') ?: 'no-reply@mthglobal.com',
        'from_name' => 'MTH GLOBAL RESOURCES',
    ],
    'paystack' => [
        'secret_key' => getenv('PAYSTACK_SECRET') ?: '',
    ],
    'groq' => [
        'api_key' => getenv('GROQ_API_KEY') ?: '',
    ],
    'vapid' => [
        'public_key' => 'BH2r-koIpv02Kp9oqRlDbuuXUE1u3RE6Ihdtu7fi61X75ZYmXWgyF5-8nCe6SYqdSYdMlJl0oprIhRv7WEE58SA',
        'private_key' => getenv('VAPID_PRIVATE_KEY') ?: '',
    ],
];

// Load local configuration overrides if available
if (file_exists(__DIR__ . '/config.local.php')) {
    $localConfig = require __DIR__ . '/config.local.php';
    $config = array_replace_recursive($config, $localConfig);
}

return $config;