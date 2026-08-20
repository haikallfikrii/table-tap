<?php
/**
 * Owner — customer menu categories (Pro: Western, Burger, Top picks, …).
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';
require_once dirname(__DIR__, 2) . '/includes/menu_categories.php';

requireLogin(['owner']);

$user = currentUser();
$shopId = requireShopId();
$lang = currentLang();
$config = getConfig();
$pageTitle = t('manage_menu_categories');
$showSound = false;
$adminScripts = [];
$nav = 'categories';
$error = '';
$pdo = db();
$shop = getShopOrFail($shopId);
$canCustom = shopHasFeature($shop, 'custom_menu_categories');

ensureShopMenuCategories($shopId);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            if (!$canCustom) {
                throw new RuntimeException(t('menu_categories_upgrade'));
            }
            if (countCustomMenuCategories($shopId) >= maxCustomMenuCategories()) {
                throw new RuntimeException(t('menu_categories_max'));
            }
            $namaMy = trim((string) ($_POST['nama_my'] ?? ''));
            $namaEn = trim((string) ($_POST['nama_en'] ?? ''));
            $kind = ($_POST['kind'] ?? '') === 'minuman' ? 'minuman' : 'makanan';
            if ($namaMy === '' || $namaEn === '') {
                throw new RuntimeException(t('error_generic'));
            }
            $kod = categoryKodFromName($namaMy);
            $exists = $pdo->prepare('SELECT id FROM menu_categories WHERE shop_id = ? AND kod = ? LIMIT 1');
            $exists->execute([$shopId, $kod]);
            if ($exists->fetch()) {
                $kod = 'kategori-' . bin2hex(random_bytes(3));
            }
            $ord = $pdo->prepare('SELECT COALESCE(MAX(urutan), 0) + 1 FROM menu_categories WHERE shop_id = ?');
            $ord->execute([$shopId]);
            $pdo->prepare(
                'INSERT INTO menu_categories (shop_id, kod, nama_my, nama_en, kind, is_system, urutan, is_active)
                 VALUES (?, ?, ?, ?, ?, 0, ?, 1)'
            )->execute([$shopId, $kod, $namaMy, $namaEn, $kind, (int) $ord->fetchColumn()]);
            redirect(baseUrl('admin/owner/categories.php?ok=1'));
        }

        if ($action === 'update') {
            $id = (int) ($_POST['id'] ?? 0);
            $cat = findShopMenuCategory($shopId, $id);
            if (!$cat) {
                throw new RuntimeException(t('no_data'));
            }
            $namaMy = trim((string) ($_POST['nama_my'] ?? ''));
            $namaEn = trim((string) ($_POST['nama_en'] ?? ''));
            $urutan = (int) ($_POST['urutan'] ?? 0);
            $kind = ($_POST['kind'] ?? menuCategoryKind($cat)) === 'minuman' ? 'minuman' : 'makanan';
            if ($namaMy === '' || $namaEn === '') {
                throw new RuntimeException(t('error_generic'));
            }
            $pdo->prepare(
                'UPDATE menu_categories SET nama_my = ?, nama_en = ?, kind = ?, urutan = ? WHERE id = ? AND shop_id = ?'
            )->execute([$namaMy, $namaEn, $kind, $urutan, $id, $shopId]);
            if (menuCategoryColumnExists()) {
                $pdo->prepare(
                    'UPDATE menu_items SET kategori = ? WHERE shop_id = ? AND menu_category_id = ?'
                )->execute([$kind, $shopId, $id]);
            }
            redirect(baseUrl('admin/owner/categories.php?ok=1'));
        }

        if ($action === 'delete') {
            if (!$canCustom) {
                throw new RuntimeException(t('menu_categories_upgrade'));
            }
            $id = (int) ($_POST['id'] ?? 0);
            $cat = findShopMenuCategory($shopId, $id);
            if (!$cat || (int) $cat['is_system'] === 1) {
                throw new RuntimeException(t('cannot_delete_system_category'));
            }
            $fallbackKod = menuCategoryKind($cat) === 'minuman' ? 'minuman' : 'makanan';
            $fallback = menuCategoryByKod($shopId, $fallbackKod);
            $fid = $fallback ? (int) $fallback['id'] : null;
            if (menuCategoryColumnExists() && $fid) {
                $pdo->prepare(
                    'UPDATE menu_items SET menu_category_id = ?, kategori = ? WHERE shop_id = ? AND menu_category_id = ?'
                )->execute([$fid, $fallbackKod, $shopId, $id]);
            }
            $pdo->prepare('DELETE FROM menu_categories WHERE id = ? AND shop_id = ? AND is_system = 0')
                ->execute([$id, $shopId]);
            redirect(baseUrl('admin/owner/categories.php?ok=1'));
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$categories = shopMenuCategories($shopId, false);
$editId = (int) ($_GET['edit'] ?? 0);
$editing = $editId > 0 ? findShopMenuCategory($shopId, $editId) : null;

$countsStmt = $pdo->prepare(
    'SELECT menu_category_id, COUNT(*) AS n FROM menu_items WHERE shop_id = ? AND is_active = 1 GROUP BY menu_category_id'
);
$countsStmt->execute([$shopId]);
$itemCounts = [];
foreach ($countsStmt->fetchAll() as $row) {
    $itemCounts[(int) $row['menu_category_id']] = (int) $row['n'];
}
?>
<?php require dirname(__DIR__, 2) . '/includes/admin_header.php'; ?>
<?php require __DIR__ . '/_nav.php'; ?>

<?php if (isset($_GET['ok'])): ?>
  <div class="stat-card" style="margin-bottom:16px;border-color:var(--success)"><?= e(t('saved')) ?></div>
<?php endif; ?>
<?php if ($error): ?><div class="form-error"><?= e($error) ?></div><?php endif; ?>

<p class="section-desc"><?= e(t('menu_categories_hint')) ?></p>

<?php if ($editing): ?>
<div class="order-card" style="margin-bottom:20px;border-color:var(--accent)">
  <h2 style="margin-top:0"><?= e(t('edit_menu_category')) ?> · <?= e(menuCategoryLabel($editing, $lang)) ?></h2>
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
      <label><?= e(t('category_kind')) ?></label>
      <select name="kind">
        <option value="makanan" <?= menuCategoryKind($editing) === 'makanan' ? 'selected' : '' ?>><?= e(t('makanan')) ?></option>
        <option value="minuman" <?= menuCategoryKind($editing) === 'minuman' ? 'selected' : '' ?>><?= e(t('minuman')) ?></option>
      </select>
    </div>
    <div class="form-group" style="margin:0">
      <label>Urutan</label>
      <input type="number" name="urutan" value="<?= e((string) $editing['urutan']) ?>">
    </div>
    <div style="display:flex;align-items:end;gap:8px">
      <button type="submit" class="btn btn-primary"><?= e(t('save')) ?></button>
      <a class="btn btn-ghost btn-sm" href="<?= e(baseUrl('admin/owner/categories.php')) ?>"><?= e(t('cancel')) ?></a>
    </div>
  </form>
</div>
<?php endif; ?>

<?php if ($canCustom): ?>
<div class="order-card" style="margin-bottom:20px">
  <h2 style="margin-top:0"><?= e(t('add_menu_category')) ?></h2>
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
    <div class="form-group" style="margin:0">
      <label><?= e(t('category_kind')) ?></label>
      <select name="kind">
        <option value="makanan"><?= e(t('makanan')) ?></option>
        <option value="minuman"><?= e(t('minuman')) ?></option>
      </select>
      <p class="order-meta" style="margin:6px 0 0"><?= e(t('category_kind_hint')) ?></p>
    </div>
    <div style="display:flex;align-items:end">
      <button type="submit" class="btn btn-primary"><?= e(t('save')) ?></button>
    </div>
  </form>
</div>
<?php else: ?>
  <p class="order-meta" style="margin-bottom:16px"><?= e(t('menu_categories_upgrade')) ?></p>
<?php endif; ?>

<div class="table-list-wrap">
  <table class="table-list">
    <thead>
      <tr>
        <th><?= e(t('name_my')) ?></th>
        <th><?= e(t('name_en')) ?></th>
        <th><?= e(t('category_kind')) ?></th>
        <th><?= e(t('manage_menu')) ?></th>
        <th><?= e(t('actions')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($categories as $cat): ?>
        <?php $cid = (int) $cat['id']; ?>
        <tr>
          <td>
            <strong><?= e((string) $cat['nama_my']) ?></strong>
            <?php if ((int) $cat['is_system'] === 1): ?>
              <span class="order-meta">(<?= e(t('station_system')) ?>)</span>
            <?php endif; ?>
          </td>
          <td><?= e((string) $cat['nama_en']) ?></td>
          <td><?= e(menuCategoryKind($cat) === 'minuman' ? t('minuman') : t('makanan')) ?></td>
          <td><?= (int) ($itemCounts[$cid] ?? 0) ?> <?= e(t('ops_items')) ?></td>
          <td style="white-space:nowrap">
            <a class="btn btn-ghost btn-sm" href="<?= e(baseUrl('admin/owner/categories.php?edit=' . $cid)) ?>"><?= e(t('edit')) ?></a>
            <?php if ((int) $cat['is_system'] !== 1): ?>
              <form method="post" style="display:inline" onsubmit="return confirm(<?= json_encode(t('confirm_delete_menu_category'), JSON_UNESCAPED_UNICODE) ?>);">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $cid ?>">
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
