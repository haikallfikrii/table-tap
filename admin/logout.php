<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

logoutUser();
redirect(baseUrl('admin/login.php'));
