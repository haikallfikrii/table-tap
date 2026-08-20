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
    static $stationHydrated = false;
    if (!$stationHydrated && !array_key_exists('station_id', $_SESSION)) {
        $stationHydrated = true;
        try {
            require_once __DIR__ . '/stations.php';
            if (userStationColumnExists()) {
                $st = db()->prepare('SELECT station_id FROM users WHERE id = ? LIMIT 1');
                $st->execute([(int) $_SESSION['user_id']]);
                $row = $st->fetch();
                $_SESSION['station_id'] = ($row && $row['station_id'] !== null) ? (int) $row['station_id'] : null;
            } else {
                $_SESSION['station_id'] = null;
            }
        } catch (Throwable $e) {
            $_SESSION['station_id'] = null;
        }
    }
    return [
        'id'       => (int) $_SESSION['user_id'],
        'username' => (string) ($_SESSION['username'] ?? ''),
        'role'     => (string) ($_SESSION['role'] ?? ''),
        'nama'     => (string) ($_SESSION['nama_paparan'] ?? ''),
        'shop_id'  => isset($_SESSION['shop_id']) && $_SESSION['shop_id'] !== null
            ? (int) $_SESSION['shop_id']
            : null,
        'shop_name'=> (string) ($_SESSION['shop_name'] ?? ''),
        'station_id' => isset($_SESSION['station_id']) && $_SESSION['station_id'] !== null
            ? (int) $_SESSION['station_id']
            : null,
    ];
}

function currentShopId(): ?int
{
    $user = currentUser();
    return $user['shop_id'] ?? null;
}

function currentShop(): ?array
{
    $shopId = currentShopId();
    if ($shopId === null) {
        return null;
    }
    return findShopById($shopId);
}

function loginUser(string $username, string $password): bool
{
    require_once __DIR__ . '/stations.php';
    $stationSelect = userStationColumnExists() ? ', u.station_id' : '';
    $stmt = db()->prepare(
        "SELECT u.id, u.username, u.password_hash, u.role, u.nama_paparan, u.shop_id{$stationSelect},
                s.nama_kedai, s.status AS shop_status
         FROM users u
         LEFT JOIN shops s ON s.id = u.shop_id
         WHERE u.username = ? AND u.is_active = 1
         LIMIT 1"
    );
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    // Block staff of inactive shops
    if ($user['role'] !== 'master') {
        if (empty($user['shop_id']) || ($user['shop_status'] ?? '') !== 'aktif') {
            return false;
        }
    }

    startAppSession();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['nama_paparan'] = $user['nama_paparan'] ?: $user['username'];
    $_SESSION['shop_id'] = $user['shop_id'] !== null ? (int) $user['shop_id'] : null;
    $_SESSION['shop_name'] = $user['nama_kedai'] ?? '';
    $_SESSION['station_id'] = isset($user['station_id']) && $user['station_id'] !== null
        ? (int) $user['station_id']
        : null;

    // Opportunistic retention cleanup for this shop (lightweight)
    if (!empty($user['shop_id'])) {
        try {
            purgeExpiredOrderHistory((int) $user['shop_id']);
        } catch (Throwable $e) {
            // ignore cleanup errors on login
        }
    }

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
 * - master: only pages that allow 'master' (or empty roles)
 * - owner: can access all shop staff pages for their shop
 */
function requireLogin(array $roles = []): void
{
    $user = currentUser();
    if (!$user) {
        redirect(baseUrl('admin/login.php'));
    }

    if ($user['role'] === 'master') {
        if ($roles !== [] && !in_array('master', $roles, true)) {
            redirect(roleHome('master'));
        }
        return;
    }

    if (empty($user['shop_id'])) {
        http_response_code(403);
        exit('Akses ditolak / Access denied.');
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

    if ($user['role'] === 'master') {
        if ($roles !== [] && !in_array('master', $roles, true)) {
            jsonError('Forbidden', 403);
        }
        return $user;
    }

    if (empty($user['shop_id'])) {
        jsonError('Forbidden', 403);
    }

    if ($roles !== [] && $user['role'] !== 'owner' && !in_array($user['role'], $roles, true)) {
        jsonError('Forbidden', 403);
    }

    return $user;
}

function requireShopId(): int
{
    $id = currentShopId();
    if ($id === null) {
        http_response_code(403);
        exit('Shop context required.');
    }
    return $id;
}

function requireShopIdApi(): int
{
    $id = currentShopId();
    if ($id === null) {
        jsonError('Shop context required', 403);
    }
    return $id;
}

function roleHome(string $role): string
{
    return match ($role) {
        'master'  => baseUrl('admin/master/index.php'),
        'kasir'   => baseUrl('admin/kasir.php'),
        'dapur'   => baseUrl('admin/dapur.php'),
        'minuman' => baseUrl('admin/minuman.php'),
        'waiter'  => baseUrl('admin/waiter.php'),
        default   => baseUrl('admin/owner/index.php'),
    };
}
