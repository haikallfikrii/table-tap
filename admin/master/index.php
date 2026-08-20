<?php
/**
 * Master admin — list & register shops
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';

requireLogin(['master']);

$user = currentUser();
$lang = currentLang();
$config = getConfig();
$pageTitle = t('master_title');
$showSound = false;
$adminScripts = [];
$error = '';
$pdo = db();

$packages = $pdo->query('SELECT * FROM packages WHERE is_active = 1 ORDER BY harga_bulanan')->fetchAll();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create_shop') {
            $namaKedai = trim((string) ($_POST['nama_kedai'] ?? ''));
            $slug = trim((string) ($_POST['slug'] ?? ''));
            $packageId = (int) ($_POST['package_id'] ?? 0);
            $ownerUser = trim((string) ($_POST['owner_username'] ?? ''));
            $ownerPass = (string) ($_POST['owner_password'] ?? '');
            $ownerNama = trim((string) ($_POST['owner_nama'] ?? ''));
            $sstEnabled = isset($_POST['sst_enabled']) ? 1 : 0;
            $sstRate = (float) ($_POST['sst_rate'] ?? 6);

            if ($namaKedai === '' || $packageId <= 0 || $ownerUser === '' || strlen($ownerPass) < 6) {
                throw new RuntimeException(t('master_create_invalid'));
            }
            if ($slug === '') {
                $slug = slugify($namaKedai);
            } else {
                $slug = slugify($slug);
            }
            if ($sstRate < 0 || $sstRate > 100) {
                throw new RuntimeException('SST rate invalid');
            }

            // unique checks
            $chk = $pdo->prepare('SELECT id FROM shops WHERE slug = ? LIMIT 1');
            $chk->execute([$slug]);
            if ($chk->fetch()) {
                throw new RuntimeException(t('slug_taken'));
            }
            $chkU = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
            $chkU->execute([$ownerUser]);
            if ($chkU->fetch()) {
                throw new RuntimeException(t('username_taken'));
            }

            $pkg = $pdo->prepare('SELECT id FROM packages WHERE id = ? AND is_active = 1');
            $pkg->execute([$packageId]);
            if (!$pkg->fetch()) {
                throw new RuntimeException('Invalid package');
            }

            $pdo->beginTransaction();
            $ins = $pdo->prepare(
                'INSERT INTO shops (nama_kedai, slug, package_id, sst_enabled, sst_rate, status)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $ins->execute([$namaKedai, $slug, $packageId, $sstEnabled, $sstRate, 'aktif']);
            $shopId = (int) $pdo->lastInsertId();

            $hash = password_hash($ownerPass, PASSWORD_DEFAULT);
            $insU = $pdo->prepare(
                'INSERT INTO users (shop_id, username, password_hash, role, nama_paparan)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $insU->execute([$shopId, $ownerUser, $hash, 'owner', $ownerNama ?: $namaKedai]);

            // Default 5 tables
            $insT = $pdo->prepare(
                'INSERT INTO tables (shop_id, nomor_meja, token_akses, status) VALUES (?, ?, ?, ?)'
            );
            for ($i = 1; $i <= 5; $i++) {
                $insT->execute([$shopId, (string) $i, generateToken(24), 'aktif']);
            }

            $pdo->commit();
            require_once dirname(__DIR__, 2) . '/includes/stations.php';
            ensureShopStations($shopId);
            redirect(baseUrl('admin/master/index.php?ok=1'));
        }

        if ($action === 'toggle_status') {
            $id = (int) ($_POST['id'] ?? 0);
            $pdo->prepare(
                "UPDATE shops SET status = IF(status = 'aktif', 'suspended', 'aktif') WHERE id = ?"
            )->execute([$id]);
            redirect(baseUrl('admin/master/index.php?ok=1'));
        }

        if ($action === 'change_package') {
            $id = (int) ($_POST['id'] ?? 0);
            $packageId = (int) ($_POST['package_id'] ?? 0);
            $pdo->prepare('UPDATE shops SET package_id = ? WHERE id = ?')->execute([$packageId, $id]);
            redirect(baseUrl('admin/master/index.php?ok=1'));
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

$shops = $pdo->query(
    "SELECT s.*, p.nama_my AS package_nama, p.nama_en AS package_nama_en, p.retention_days,
            (SELECT COUNT(*) FROM users u WHERE u.shop_id = s.id) AS user_count
     FROM shops s
     INNER JOIN packages p ON p.id = s.package_id
     ORDER BY s.created_at DESC"
)->fetchAll();
?>
<?php require dirname(__DIR__, 2) . '/includes/admin_header.php'; ?>

<?php if (isset($_GET['ok'])): ?>
  <div class="stat-card" style="margin-bottom:16px;border-color:var(--success)"><?= e(t('saved')) ?></div>
<?php endif; ?>
<?php if ($error): ?><div class="form-error"><?= e($error) ?></div><?php endif; ?>

<div class="stat-row">
  <div class="stat-card">
    <div class="label"><?= e(t('shops')) ?></div>
    <div class="value"><?= count($shops) ?></div>
  </div>
  <div class="stat-card">
    <div class="label"><?= e(t('packages')) ?></div>
    <div class="value"><?= count($packages) ?></div>
  </div>
</div>

<div class="order-card" style="margin-bottom:20px">
  <h2 style="margin-top:0"><?= e(t('register_shop')) ?></h2>
  <form method="post" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
    <input type="hidden" name="action" value="create_shop">
    <div class="form-group" style="margin:0">
      <label><?= e(t('shop_name')) ?> *</label>
      <input name="nama_kedai" required placeholder="Kedai Makan Ahmad">
    </div>
    <div class="form-group" style="margin:0">
      <label>Slug (URL)</label>
      <input name="slug" placeholder="auto">
    </div>
    <div class="form-group" style="margin:0">
      <label><?= e(t('package')) ?> *</label>
      <select name="package_id" required>
        <?php foreach ($packages as $p): ?>
          <option value="<?= (int) $p['id'] ?>">
            <?= e($lang === 'en' ? $p['nama_en'] : $p['nama_my']) ?>
            (<?= $p['retention_days'] === null ? t('retention_forever') : ((int) $p['retention_days'] . ' ' . t('days')) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0">
      <label><?= e(t('owner')) ?> username *</label>
      <input name="owner_username" required autocomplete="off">
    </div>
    <div class="form-group" style="margin:0">
      <label><?= e(t('owner')) ?> password *</label>
      <input type="password" name="owner_password" required minlength="6">
    </div>
    <div class="form-group" style="margin:0">
      <label><?= e(t('owner')) ?> nama</label>
      <input name="owner_nama">
    </div>
    <div class="form-group" style="margin:0">
      <label><?= e(t('sst_rate')) ?> (%)</label>
      <input type="number" step="0.01" min="0" max="100" name="sst_rate" value="6.00">
    </div>
    <div class="form-group" style="margin:0;display:flex;align-items:end">
      <label style="display:flex;gap:8px;align-items:center;font-weight:600">
        <input type="checkbox" name="sst_enabled" value="1"> <?= e(t('sst_enable')) ?>
      </label>
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
        <th><?= e(t('shop_name')) ?></th>
        <th><?= e(t('package')) ?></th>
        <th>SST</th>
        <th><?= e(t('status')) ?></th>
        <th><?= e(t('actions')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($shops as $s): ?>
        <tr>
          <td>
            <strong><?= e($s['nama_kedai']) ?></strong><br>
            <span class="order-meta"><?= e($s['slug']) ?> · <?= (int) $s['user_count'] ?> users</span>
          </td>
          <td>
            <?= e($lang === 'en' ? $s['package_nama_en'] : $s['package_nama']) ?><br>
            <span class="order-meta">
              <?= $s['retention_days'] === null ? t('retention_forever') : ((int) $s['retention_days'] . ' ' . t('days')) ?>
            </span>
            <form method="post" style="margin-top:6px;display:flex;gap:6px;align-items:center">
              <input type="hidden" name="action" value="change_package">
              <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
              <select name="package_id" style="min-height:36px;padding:4px 8px">
                <?php foreach ($packages as $p): ?>
                  <option value="<?= (int) $p['id'] ?>" <?= (int) $p['id'] === (int) $s['package_id'] ? 'selected' : '' ?>>
                    <?= e($lang === 'en' ? $p['nama_en'] : $p['nama_my']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-ghost btn-sm"><?= e(t('save')) ?></button>
            </form>
          </td>
          <td>
            <?= (int) $s['sst_enabled'] ? e(number_format((float) $s['sst_rate'], 2) . '%') : '—' ?>
          </td>
          <td>
            <span class="badge <?= $s['status'] === 'aktif' ? 'badge-selesai' : 'badge-belum_bayar' ?>">
              <?= e($s['status']) ?>
            </span>
          </td>
          <td>
            <form method="post">
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
              <button type="submit" class="btn btn-secondary btn-sm">
                <?= $s['status'] === 'aktif' ? 'Suspend' : 'Activate' ?>
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require dirname(__DIR__, 2) . '/includes/admin_footer.php'; ?>
