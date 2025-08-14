<?php
// Load environment variables
require_once dirname(__DIR__) . '/app/Core/Env.php';
\App\Core\Env::load();

return [
    'host' => \App\Core\Env::get('DB_HOST', 'localhost'),
    'dbname' => \App\Core\Env::get('DB_DATABASE', 'khan'),
    'username' => \App\Core\Env::get('DB_USERNAME', 'khan'),
    'password' => \App\Core\Env::get('DB_PASSWORD', ''),
    'charset' => \App\Core\Env::get('DB_CHARSET', 'utf8mb4'),
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];