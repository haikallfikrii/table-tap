<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

startAppSession();
$user = currentUser();
if ($user) {
    redirect(roleHome($user['role']));
}
redirect(baseUrl('admin/login.php'));
