<?php
return [
    'name' => getenv('APP_NAME') ?: 'China Office Accounting System',
    'url' => getenv('APP_URL') ?: 'https://china.ababel.net',
    'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Shanghai',
    'locale' => getenv('APP_LOCALE') ?: 'ar',
    'fallback_locale' => getenv('APP_FALLBACK_LOCALE') ?: 'en',
    'currencies' => ['RMB', 'USD', 'SDG', 'AED'],
    'default_currency' => getenv('DEFAULT_CURRENCY') ?: 'RMB',
    'port_sudan_api_url' => getenv('PORT_SUDAN_API_URL') ?: 'https://ababel.net/app/api/china_sync.php',
    'port_sudan_api_key' => getenv('PORT_SUDAN_API_KEY') ?: '',
    'webhook_api_key' => getenv('WEBHOOK_API_KEY') ?: '',
];