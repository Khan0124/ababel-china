<?php
return [
    'host' => 'localhost',
    'dbname' => 'china_ababel',
    'username' => 'china_ababel',
    'password' => 'Khan@70990100',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];