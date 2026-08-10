<?php
/**
 * Owner — staff user management
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

$roles = ['owner', 'kasir', 'dapur', 'minuman'];

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
        if ($action === 'toggle') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id === (int) $user['id']) {
                throw new RuntimeException('Tidak boleh nyahaktif akaun sendiri');
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
    } catch (Throwable $e) {
        $error = $e->getMessage();
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
  <div class="stat-card" style="margin-bottom:16px;border-color:var(--success)"><?= e(currentLang() === 'en' ? 'Saved.' : 'Disimpan.') ?></div>
<?php endif; ?>
<?php if ($error): ?><div class="form-error"><?= e($error) ?></div><?php endif; ?>

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
          <option value="<?= e($r) ?>"><?= e($r) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0">
      <label>Nama</label>
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
        <th>Nama</th>
        <th><?= e(t('status')) ?></th>
        <th><?= e(t('actions')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= e($u['username']) ?></td>
          <td><?= e($u['role']) ?></td>
          <td><?= e($u['nama_paparan'] ?: '—') ?></td>
          <td>
            <span class="badge <?= (int) $u['is_active'] ? 'badge-selesai' : 'badge-belum_bayar' ?>">
              <?= (int) $u['is_active'] ? 'aktif' : 'off' ?>
            </span>
          </td>
          <td>
            <form method="post" style="display:inline-flex;flex-wrap:wrap;gap:6px;align-items:center">
              <input type="hidden" name="action" value="reset_password">
              <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
              <input type="password" name="password" placeholder="password baru" minlength="6" style="min-height:40px;padding:6px 10px;border:1px solid var(--border);border-radius:8px;width:140px">
              <button type="submit" class="btn btn-secondary btn-sm">Reset PW</button>
            </form>
            <?php if ((int) $u['id'] !== (int) $user['id']): ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                <button type="submit" class="btn btn-ghost btn-sm"><?= (int) $u['is_active'] ? 'Off' : 'On' ?></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require dirname(__DIR__, 2) . '/includes/admin_footer.php'; ?>
