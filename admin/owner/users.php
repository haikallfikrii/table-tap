<?php
/**
 * Owner — staff user management (create, edit role, delete)
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';

requireLogin(['owner']);

$user = currentUser();
$shopId = requireShopId();
$lang = currentLang();
$config = getConfig();
$pageTitle = t('manage_users');
$showSound = false;
$adminScripts = [];
$nav = 'users';
$error = '';
$pdo = db();

$roles = ['owner', 'kasir', 'dapur', 'minuman', 'waiter'];

function roleLabel(string $role): string
{
    $key = 'role_' . $role;
    $label = t($key);
    return $label === $key ? $role : $label;
}

function countActiveOwners(PDO $pdo, int $shopId, ?int $exceptId = null): int
{
    $sql = "SELECT COUNT(*) FROM users WHERE shop_id = ? AND role = 'owner' AND is_active = 1";
    $params = [$shopId];
    if ($exceptId !== null) {
        $sql .= ' AND id != ?';
        $params[] = $exceptId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function findShopUser(PDO $pdo, int $shopId, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, username, role, nama_paparan, is_active FROM users WHERE id = ? AND shop_id = ? LIMIT 1'
    );
    $stmt->execute([$id, $shopId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

$editId = (int) ($_GET['edit'] ?? 0);
$editing = $editId > 0 ? findShopUser($pdo, $shopId, $editId) : null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $role = (string) ($_POST['role'] ?? '');
            $nama = trim((string) ($_POST['nama_paparan'] ?? ''));
            if ($username === '' || strlen($password) < 6 || !in_array($role, $roles, true)) {
                throw new RuntimeException('Username, role & password (min 6) wajib');
            }
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                'INSERT INTO users (shop_id, username, password_hash, role, nama_paparan) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$shopId, $username, $hash, $role, $nama ?: null]);
            redirect(baseUrl('admin/owner/users.php?ok=1'));
        }

        if ($action === 'update') {
            $id = (int) ($_POST['id'] ?? 0);
            $target = findShopUser($pdo, $shopId, $id);
            if (!$target) {
                throw new RuntimeException(t('no_data'));
            }
            $username = trim((string) ($_POST['username'] ?? ''));
            $nama = trim((string) ($_POST['nama_paparan'] ?? ''));
            $role = (string) ($_POST['role'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            if ($username === '' || !in_array($role, $roles, true)) {
                throw new RuntimeException(t('error_generic'));
            }
            if ($target['role'] === 'owner' && $role !== 'owner' && countActiveOwners($pdo, $shopId, $id) < 1) {
                throw new RuntimeException(t('cannot_demote_last_owner'));
            }
            $taken = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1');
            $taken->execute([$username, $id]);
            if ($taken->fetch()) {
                throw new RuntimeException(t('username_taken'));
            }

            if ($password !== '') {
                if (strlen($password) < 6) {
                    throw new RuntimeException('Password min 6 aksara');
                }
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare(
                    'UPDATE users SET username = ?, nama_paparan = ?, role = ?, password_hash = ? WHERE id = ? AND shop_id = ?'
                )->execute([$username, $nama ?: null, $role, $hash, $id, $shopId]);
            } else {
                $pdo->prepare(
                    'UPDATE users SET username = ?, nama_paparan = ?, role = ? WHERE id = ? AND shop_id = ?'
                )->execute([$username, $nama ?: null, $role, $id, $shopId]);
            }

            if ($id === (int) $user['id']) {
                startAppSession();
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $role;
                $_SESSION['nama_paparan'] = $nama !== '' ? $nama : $username;
                if ($role !== 'owner') {
                    redirect(roleHome($role));
                }
            }
            redirect(baseUrl('admin/owner/users.php?ok=1'));
        }

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id === (int) $user['id']) {
                throw new RuntimeException(t('cannot_delete_self'));
            }
            $target = findShopUser($pdo, $shopId, $id);
            if (!$target) {
                throw new RuntimeException(t('no_data'));
            }
            if ($target['role'] === 'owner' && countActiveOwners($pdo, $shopId, $id) < 1) {
                throw new RuntimeException(t('cannot_delete_last_owner'));
            }
            $pdo->prepare('DELETE FROM users WHERE id = ? AND shop_id = ?')->execute([$id, $shopId]);
            redirect(baseUrl('admin/owner/users.php?ok=1'));
        }

        if ($action === 'toggle') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id === (int) $user['id']) {
                throw new RuntimeException(t('cannot_delete_self'));
            }
            $target = findShopUser($pdo, $shopId, $id);
            if (!$target) {
                throw new RuntimeException(t('no_data'));
            }
            if ($target['role'] === 'owner' && (int) $target['is_active'] === 1 && countActiveOwners($pdo, $shopId, $id) < 1) {
                throw new RuntimeException(t('cannot_delete_last_owner'));
            }
            $pdo->prepare(
                'UPDATE users SET is_active = IF(is_active=1,0,1) WHERE id = ? AND shop_id = ?'
            )->execute([$id, $shopId]);
            redirect(baseUrl('admin/owner/users.php?ok=1'));
        }

        if ($action === 'reset_password') {
            $id = (int) ($_POST['id'] ?? 0);
            $password = (string) ($_POST['password'] ?? '');
            if ($id <= 0 || strlen($password) < 6) {
                throw new RuntimeException('Password min 6 aksara');
            }
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare(
                'UPDATE users SET password_hash = ? WHERE id = ? AND shop_id = ?'
            )->execute([$hash, $id, $shopId]);
            redirect(baseUrl('admin/owner/users.php?ok=1'));
        }
    } catch (PDOException $e) {
        if ((int) $e->getCode() === 23000) {
            $error = t('username_taken');
        } else {
            $error = $e->getMessage();
        }
        if ($action === 'update') {
            $editing = findShopUser($pdo, $shopId, (int) ($_POST['id'] ?? 0));
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
        if ($action === 'update') {
            $editing = findShopUser($pdo, $shopId, (int) ($_POST['id'] ?? 0));
        }
    }
}

$usersStmt = $pdo->prepare(
    'SELECT id, username, role, nama_paparan, is_active, created_at
     FROM users WHERE shop_id = ? ORDER BY role, username'
);
$usersStmt->execute([$shopId]);
$users = $usersStmt->fetchAll();
?>
<?php require dirname(__DIR__, 2) . '/includes/admin_header.php'; ?>
<?php require __DIR__ . '/_nav.php'; ?>

<?php if (isset($_GET['ok'])): ?>
  <div class="stat-card" style="margin-bottom:16px;border-color:var(--success)"><?= e(t('saved')) ?></div>
<?php endif; ?>
<?php if ($error): ?><div class="form-error"><?= e($error) ?></div><?php endif; ?>

<?php if ($editing): ?>
<div class="order-card" style="margin-bottom:20px;border-color:var(--accent)">
  <h2 style="margin-top:0"><?= e(t('edit_user')) ?> · <?= e($editing['username']) ?></h2>
  <form method="post" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
    <div class="form-group" style="margin:0">
      <label><?= e(t('username')) ?></label>
      <input name="username" required value="<?= e($editing['username']) ?>" autocomplete="off">
    </div>
    <div class="form-group" style="margin:0">
      <label><?= e(t('display_name')) ?></label>
      <input name="nama_paparan" value="<?= e((string) $editing['nama_paparan']) ?>">
    </div>
    <div class="form-group" style="margin:0">
      <label><?= e(t('role')) ?></label>
      <select name="role">
        <?php foreach ($roles as $r): ?>
          <option value="<?= e($r) ?>" <?= $editing['role'] === $r ? 'selected' : '' ?>><?= e(roleLabel($r)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0">
      <label><?= e(t('new_password')) ?></label>
      <input type="password" name="password" minlength="6" autocomplete="new-password" placeholder="<?= e(t('optional')) ?>">
      <p class="order-meta" style="margin:6px 0 0"><?= e(t('leave_blank_password')) ?></p>
    </div>
    <div style="display:flex;align-items:end;gap:8px;flex-wrap:wrap">
      <button type="submit" class="btn btn-primary"><?= e(t('save')) ?></button>
      <a class="btn btn-ghost btn-sm" href="<?= e(baseUrl('admin/owner/users.php')) ?>"><?= e(t('cancel')) ?></a>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="order-card" style="margin-bottom:20px">
  <h2 style="margin-top:0"><?= e(t('add_user')) ?></h2>
  <form method="post" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px">
    <input type="hidden" name="action" value="create">
    <div class="form-group" style="margin:0">
      <label><?= e(t('username')) ?></label>
      <input name="username" required autocomplete="off">
    </div>
    <div class="form-group" style="margin:0">
      <label><?= e(t('password')) ?></label>
      <input type="password" name="password" required minlength="6">
    </div>
    <div class="form-group" style="margin:0">
      <label><?= e(t('role')) ?></label>
      <select name="role">
        <?php foreach ($roles as $r): ?>
          <option value="<?= e($r) ?>"><?= e(roleLabel($r)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0">
      <label><?= e(t('display_name')) ?></label>
      <input name="nama_paparan">
    </div>
    <div style="display:flex;align-items:end">
      <button type="submit" class="btn btn-primary"><?= e(t('save')) ?></button>
    </div>
  </form>
</div>

<div class="table-list-wrap">
  <table class="table-list">
    <thead>
      <tr>
        <th><?= e(t('username')) ?></th>
        <th><?= e(t('role')) ?></th>
        <th><?= e(t('display_name')) ?></th>
        <th><?= e(t('status')) ?></th>
        <th><?= e(t('actions')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <?php $isSelf = (int) $u['id'] === (int) $user['id']; ?>
        <tr>
          <td><?= e($u['username']) ?><?php if ($isSelf): ?> <span class="order-meta">(<?= e($lang === 'en' ? 'you' : 'anda') ?>)</span><?php endif; ?></td>
          <td><?= e(roleLabel((string) $u['role'])) ?></td>
          <td><?= e($u['nama_paparan'] ?: '—') ?></td>
          <td>
            <span class="badge <?= (int) $u['is_active'] ? 'badge-selesai' : 'badge-belum_bayar' ?>">
              <?= (int) $u['is_active'] ? 'aktif' : 'off' ?>
            </span>
          </td>
          <td>
            <div class="staff-actions">
              <a class="btn btn-secondary btn-sm" href="<?= e(baseUrl('admin/owner/users.php?edit=' . (int) $u['id'])) ?>"><?= e(t('edit')) ?></a>
              <?php if (!$isSelf): ?>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-sm"><?= (int) $u['is_active'] ? 'Off' : 'On' ?></button>
                </form>
                <form method="post" style="display:inline" onsubmit="return confirm(<?= json_encode(t('confirm_delete_user'), JSON_UNESCAPED_UNICODE) ?>);">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm"><?= e(t('delete')) ?></button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require dirname(__DIR__, 2) . '/includes/admin_footer.php'; ?>
