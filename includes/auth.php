<?php
/**
 * Session-based authentication for admin pages.
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function startAppSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $c = getConfig();
    session_name($c['session_name'] ?? 'tabletap_session');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

function currentUser(): ?array
{
    startAppSession();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id'       => (int) $_SESSION['user_id'],
        'username' => (string) ($_SESSION['username'] ?? ''),
        'role'     => (string) ($_SESSION['role'] ?? ''),
        'nama'     => (string) ($_SESSION['nama_paparan'] ?? ''),
    ];
}

function loginUser(string $username, string $password): bool
{
    $stmt = db()->prepare(
        'SELECT id, username, password_hash, role, nama_paparan
         FROM users
         WHERE username = ? AND is_active = 1
         LIMIT 1'
    );
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    startAppSession();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['nama_paparan'] = $user['nama_paparan'] ?: $user['username'];

    return true;
}

function logoutUser(): void
{
    startAppSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * Require login. Optionally restrict to one or more roles.
 * Owner can access all admin pages.
 */
function requireLogin(array $roles = []): void
{
    $user = currentUser();
    if (!$user) {
        redirect(baseUrl('admin/login.php'));
    }

    if ($roles !== [] && $user['role'] !== 'owner' && !in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('Akses ditolak / Access denied.');
    }
}

function requireLoginApi(array $roles = []): array
{
    $user = currentUser();
    if (!$user) {
        jsonError('Unauthorized', 401);
    }
    if ($roles !== [] && $user['role'] !== 'owner' && !in_array($user['role'], $roles, true)) {
        jsonError('Forbidden', 403);
    }
    return $user;
}

function roleHome(string $role): string
{
    return match ($role) {
        'kasir'   => baseUrl('admin/kasir.php'),
        'dapur'   => baseUrl('admin/dapur.php'),
        'minuman' => baseUrl('admin/minuman.php'),
        default   => baseUrl('admin/owner/index.php'),
    };
}
