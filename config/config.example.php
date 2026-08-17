<?php
/**
 * TableTap — Configuration Example
 * Copy to config.php and fill in Hostinger MySQL credentials.
 * NEVER commit config.php (it is gitignored).
 */

return [
    // Database (Hostinger hPanel → Databases)
    // IMPORTANT: for PHP on the same Hostinger site, use "localhost"
    // (NOT the remote host shown in phpMyAdmin / external MySQL URLs).
    'db_host' => 'localhost',
    'db_name' => 'u451240370_tabletap',
    'db_user' => 'YOUR_DB_USER',
    'db_pass' => 'YOUR_DB_PASSWORD',
    'db_charset' => 'utf8mb4',

    // App
    'app_name' => 'TableTap',
    // Fallback for cron/CLI. Browser links follow the current host (see allowed_hosts).
    'app_url' => 'https://tabletap.my', // no trailing slash
    'allowed_hosts' => [
        'tabletap.my',
        'www.tabletap.my',
        'tabletap.jomsite.com',
        'localhost',
        '127.0.0.1',
    ],
    'timezone' => 'Asia/Kuala_Lumpur',
    'currency' => 'RM',
    'currency_decimals' => 2,

    // Defaults
    'default_lang' => 'my', // my | en
    'session_name' => 'tabletap_session',

    // Polling interval for dashboards (milliseconds)
    'poll_interval_ms' => 3000,

    // Cron secret for retention cleanup (Hostinger cron → hit cron/purge_history.php?key=...)
    'cron_secret' => 'CHANGE_ME_TO_RANDOM_STRING',

    // Upload
    'upload_max_bytes' => 2 * 1024 * 1024, // 2MB
    'upload_allowed_types' => ['image/jpeg', 'image/png', 'image/webp'],

    // ChatLM popup on the public landing page (set chatlm_enabled => false to hide)
    'chatlm_enabled' => true,
    'chatlm_base_url' => 'https://chatlm.tech',
    'chatlm_api_key' => 'df78d68075cf72f71568061bf7e17726424993dcd938fb16b1331cd12dd63b41',
];
