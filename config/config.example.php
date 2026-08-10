<?php
/**
 * TableTap — Configuration Example
 * Copy this file to config.php and fill in your credentials.
 * On Hostinger: use the MySQL details from hPanel → Databases.
 */

return [
    // Database
    'db_host' => 'localhost',
    'db_name' => 'tabletap',
    'db_user' => 'root',
    'db_pass' => '',
    'db_charset' => 'utf8mb4',

    // App
    'app_name' => 'TableTap',
    'app_url' => 'https://yourdomain.com', // no trailing slash
    'timezone' => 'Asia/Kuala_Lumpur',
    'currency' => 'RM',
    'currency_decimals' => 2,

    // Defaults
    'default_lang' => 'my', // my | en
    'session_name' => 'tabletap_session',

    // Polling interval for dashboards (milliseconds)
    'poll_interval_ms' => 3000,

    // Upload
    'upload_max_bytes' => 2 * 1024 * 1024, // 2MB
    'upload_allowed_types' => ['image/jpeg', 'image/png', 'image/webp'],
];
