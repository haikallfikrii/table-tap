<?php
/**
 * Owner — work stations (kitchen / drinks / Pro custom).
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';
require_once dirname(__DIR__, 2) . '/includes/stations.php';

requireLogin(['owner']);

$user = currentUser();
$shopId = requireShopId();
$lang = currentLang();
$config = getConfig();
$pageTitle = t('manage_stations');
$showSound = false;
$adminScripts = [];
$nav = 'stations';
$error = '';
$pdo = db();
$shop = getShopOrFail($shopId);
$canCustom = shopHasFeature($shop, 'custom_stations');

ensureShopStations($shopId);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            if (!$canCustom) {
                throw new RuntimeException(t('stations_upgrade'));
            }
            if (countCustomStations($shopId) >= maxCustomStations()) {
                throw new RuntimeException(t('stations_max'));
            }
            $namaMy = trim((string) ($_POST['nama_my'] ?? ''));
            $namaEn = trim((string) ($_POST['nama_en'] ?? ''));
            if ($namaMy === '' || $namaEn === '') {
                throw new RuntimeException(t('error_generic'));
            }
            $kod = stationKodFromName($namaMy);
            $exists = $pdo->prepare('SELECT id FROM stations WHERE shop_id = ? AND kod = ? LIMIT 1');
            $exists->execute([$shopId, $kod]);
            if ($exists->fetch()) {
                $kod = 'stesen-' . bin2hex(random_bytes(3));
            }
            $ord = $pdo->prepare('SELECT COALESCE(MAX(urutan), 0) + 1 FROM stations WHERE shop_id = ?');
            $ord->execute([$shopId]);
            $pdo->prepare(
                'INSERT INTO stations (shop_id, kod, nama_my, nama_en, is_system, urutan, is_active)
                 VALUES (?, ?, ?, ?, 0, ?, 1)'
            )->execute([$shopId, $kod, $namaMy, $namaEn, (int) $ord->fetchColumn()]);
            redirect(baseUrl('admin/owner/stations.php?ok=1'));
        }

        if ($action === 'update') {
            $id = (int) ($_POST['id'] ?? 0);
            $st = findShopStation($shopId, $id);
            if (!$st) {
                throw new RuntimeException(t('no_data'));
            }
            $namaMy = trim((string) ($_POST['nama_my'] ?? ''));
            $namaEn = trim((string) ($_POST['nama_en'] ?? ''));
            $urutan = (int) ($_POST['urutan'] ?? 0);
            if ($namaMy === '' || $namaEn === '') {
                throw new RuntimeException(t('error_generic'));
            }
            $pdo->prepare(
                'UPDATE stations SET nama_my = ?, nama_en = ?, urutan = ? WHERE id = ? AND shop_id = ?'
            )->execute([$namaMy, $namaEn, $urutan, $id, $shopId]);
            redirect(baseUrl('admin/owner/stations.php?ok=1'));
        }

        if ($action === 'delete') {
            if (!$canCustom) {
                throw new RuntimeException(t('stations_upgrade'));
            }
            $id = (int) ($_POST['id'] ?? 0);
            $st = findShopStation($shopId, $id);
            if (!$st || (int) $st['is_system'] === 1) {
                throw new RuntimeException(t('cannot_delete_system_station'));
            }
            $fallback = shopStationByKod($shopId, 'dapur');
            $fid = $fallback ? (int) $fallback['id'] : null;
            if (menuStationColumnExists() && $fid) {
                $pdo->prepare('UPDATE menu_items SET station_id = ? WHERE shop_id = ? AND station_id = ?')
                    ->execute([$fid, $shopId, $id]);
            }
            if (userStationColumnExists()) {
                $pdo->prepare('UPDATE users SET station_id = NULL WHERE shop_id = ? AND station_id = ?')
                    ->execute([$shopId, $id]);
            }
            if (orderStationColumnExists() && $fid) {
                $pdo->prepare(
                    "UPDATE order_items oi
                     INNER JOIN orders o ON o.id = oi.order_id
                     SET oi.station_id_saat_order = ?
                     WHERE o.shop_id = ? AND oi.station_id_saat_order = ?
                       AND oi.status_item != 'dihantar'"
                )->execute([$fid, $shopId, $id]);
            }
            $pdo->prepare('DELETE FROM stations WHERE id = ? AND shop_id = ? AND is_system = 0')
                ->execute([$id, $shopId]);
            redirect(baseUrl('admin/owner/stations.php?ok=1'));
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$stations = shopStations($shopId, false);
$editId = (int) ($_GET['edit'] ?? 0);
$editing = $editId > 0 ? findShopStation($shopId, $editId) : null;
?>
<?php require dirname(__DIR__, 2) . '/includes/admin_header.php'; ?>
<?php require __DIR__ . '/_nav.php'; ?>

<?php if (isset($_GET['ok'])): ?>
  <div class="stat-card" style="margin-bottom:16px;border-color:var(--success)"><?= e(t('saved')) ?></div>
<?php endif; ?>
<?php if ($error): ?><div class="form-error"><?= e($error) ?></div><?php endif; ?>

<p class="section-desc"><?= e(t('stations_hint')) ?></p>

<?php if ($editing): ?>
<div class="order-card" style="margin-bottom:20px;border-color:var(--accent)">
  <h2 style="margin-top:0"><?= e(t('edit_station')) ?> · <?= e(stationLabel($editing, $lang)) ?></h2>
  <form method="post" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
    <div class="form-group" style="margin:0">
      <label><?= e(t('name_my')) ?></label>
      <input name="nama_my" required value="<?= e((string) $editing['nama_my']) ?>">
    </div>
    <div class="form-group" style="margin:0">
      <label><?= e(t('name_en')) ?></label>
      <input name="nama_en" required value="<?= e((string) $editing['nama_en']) ?>">
    </div>
    <div class="form-group" style="margin:0">
      <label>Urutan</label>
      <input type="number" name="urutan" value="<?= e((string) $editing['urutan']) ?>">
    </div>
    <div style="display:flex;align-items:end;gap:8px">
      <button type="submit" class="btn btn-primary"><?= e(t('save')) ?></button>
      <a class="btn btn-ghost btn-sm" href="<?= e(baseUrl('admin/owner/stations.php')) ?>"><?= e(t('cancel')) ?></a>
    </div>
  </form>
</div>
<?php endif; ?>

<?php if ($canCustom): ?>
<div class="order-card" style="margin-bottom:20px">
  <h2 style="margin-top:0"><?= e(t('add_station')) ?></h2>
  <form method="post" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px">
    <input type="hidden" name="action" value="create">
    <div class="form-group" style="margin:0">
      <label><?= e(t('name_my')) ?></label>
      <input name="nama_my" required placeholder="Western">
    </div>
    <div class="form-group" style="margin:0">
      <label><?= e(t('name_en')) ?></label>
      <input name="nama_en" required placeholder="Western">
    </div>
    <div style="display:flex;align-items:end">
      <button type="submit" class="btn btn-primary"><?= e(t('save')) ?></button>
    </div>
  </form>
</div>
<?php else: ?>
  <p class="order-meta" style="margin-bottom:16px"><?= e(t('stations_upgrade')) ?></p>
<?php endif; ?>

<div class="table-list-wrap">
  <table class="table-list">
    <thead>
      <tr>
        <th><?= e(t('name_my')) ?></th>
        <th><?= e(t('name_en')) ?></th>
        <th><?= e(t('actions')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($stations as $st): ?>
        <tr>
          <td>
            <strong><?= e((string) $st['nama_my']) ?></strong>
            <?php if ((int) $st['is_system'] === 1): ?>
              <span class="order-meta">(<?= e(t('station_system')) ?>)</span>
            <?php endif; ?>
          </td>
          <td><?= e((string) $st['nama_en']) ?></td>
          <td style="white-space:nowrap">
            <a class="btn btn-secondary btn-sm" href="<?= e(stationScreenUrl($st)) ?>"><?= e(t('ops_open')) ?></a>
            <a class="btn btn-ghost btn-sm" href="<?= e(baseUrl('admin/owner/stations.php?edit=' . (int) $st['id'])) ?>"><?= e(t('edit')) ?></a>
            <?php if ((int) $st['is_system'] !== 1): ?>
              <form method="post" style="display:inline" onsubmit="return confirm(<?= json_encode(t('confirm_delete_station'), JSON_UNESCAPED_UNICODE) ?>);">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $st['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm"><?= e(t('delete')) ?></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require dirname(__DIR__, 2) . '/includes/admin_footer.php'; ?>
