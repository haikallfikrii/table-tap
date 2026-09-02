<?php
/**
 * Owner — menu CRUD
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';

requireLogin(['owner']);

$user = currentUser();
$shopId = requireShopId();
$lang = currentLang();
$config = getConfig();
$pageTitle = t('manage_menu');
$showSound = false;
$adminScripts = [];
$nav = 'menu';
$flash = '';
$error = '';
$pdo = db();
$shop = getShopOrFail($shopId);
$canGallery = shopHasFeature($shop, 'menu_gallery');
$maxGallery = 6;
require_once dirname(__DIR__, 2) . '/includes/stations.php';
require_once dirname(__DIR__, 2) . '/includes/menu_categories.php';
require_once dirname(__DIR__, 2) . '/includes/menu_addons.php';
$stations = shopStations($shopId, true);
$menuCategories = shopMenuCategories($shopId, true);

function handleMenuUpload(array $file, array $config): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed');
    }
    if (($file['size'] ?? 0) > ($config['upload_max_bytes'] ?? 2097152)) {
        throw new RuntimeException('File too large (max 2MB)');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = $config['upload_allowed_types'] ?? ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowed, true)) {
        throw new RuntimeException('Invalid image type');
    }
    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => 'jpg',
    };
    $name = 'menu_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = uploadPath($name);
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save upload');
    }
    return storageUploadRel('menu', $name);
}

function saveGalleryUploads(PDO $pdo, int $shopId, int $menuId, array $files, array $config, int $max): void
{
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM menu_photos WHERE shop_id = ? AND menu_item_id = ?');
    $countStmt->execute([$shopId, $menuId]);
    $have = (int) $countStmt->fetchColumn();
    if (!isset($files['name']) || !is_array($files['name'])) {
        return;
    }
    $n = count($files['name']);
    for ($i = 0; $i < $n; $i++) {
        if ($have >= $max) {
            break;
        }
        $one = [
            'name' => $files['name'][$i] ?? '',
            'type' => $files['type'][$i] ?? '',
            'tmp_name' => $files['tmp_name'][$i] ?? '',
            'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$i] ?? 0,
        ];
        $url = handleMenuUpload($one, $config);
        if ($url) {
            $pdo->prepare(
                'INSERT INTO menu_photos (shop_id, menu_item_id, foto_url, urutan) VALUES (?, ?, ?, ?)'
            )->execute([$shopId, $menuId, $url, $have]);
            $have++;
        }
    }
}

// Actions
$action = $_POST['action'] ?? '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        if ($action === 'create' || $action === 'update') {
            $id = (int) ($_POST['id'] ?? 0);
            $namaMy = trim((string) ($_POST['nama_my'] ?? ''));
            $namaEn = trim((string) ($_POST['nama_en'] ?? ''));
            $descMy = trim((string) ($_POST['deskripsi_my'] ?? ''));
            $descEn = trim((string) ($_POST['deskripsi_en'] ?? ''));
            $harga = (float) ($_POST['harga'] ?? 0);
            $categoryId = resolveMenuCategoryId($shopId, (int) ($_POST['menu_category_id'] ?? 0));
            $category = $categoryId ? findShopMenuCategory($shopId, $categoryId) : null;
            if (!$category) {
                throw new RuntimeException(t('error_generic'));
            }
            $kategori = menuCategoryKind($category);
            $stationId = resolveMenuStationId($shopId, $kategori, (int) ($_POST['station_id'] ?? 0), $category);
            $stok = ($_POST['status_stok'] ?? '') === 'habis' ? 'habis' : 'tersedia';
            $urutan = (int) ($_POST['urutan'] ?? 0);

            if ($namaMy === '' || $namaEn === '' || $harga < 0) {
                throw new RuntimeException('Nama dan harga wajib diisi');
            }
            if (function_exists('mb_strlen')) {
                if (mb_strlen($descMy) > 2000) {
                    $descMy = mb_substr($descMy, 0, 2000);
                }
                if (mb_strlen($descEn) > 2000) {
                    $descEn = mb_substr($descEn, 0, 2000);
                }
            }

            $fotoUrl = null;
            if (!empty($_FILES['foto'])) {
                $fotoUrl = handleMenuUpload($_FILES['foto'], $config);
            }

            if ($action === 'create') {
                if (menuCategoryColumnExists() && menuStationColumnExists()) {
                    $stmt = $pdo->prepare(
                        'INSERT INTO menu_items
                         (shop_id, nama_my, nama_en, deskripsi_my, deskripsi_en, harga, kategori, menu_category_id, station_id, foto_url, status_stok, urutan)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$shopId, $namaMy, $namaEn, $descMy ?: null, $descEn ?: null, $harga, $kategori, $categoryId, $stationId, $fotoUrl, $stok, $urutan]);
                } elseif (menuCategoryColumnExists()) {
                    $stmt = $pdo->prepare(
                        'INSERT INTO menu_items
                         (shop_id, nama_my, nama_en, deskripsi_my, deskripsi_en, harga, kategori, menu_category_id, foto_url, status_stok, urutan)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$shopId, $namaMy, $namaEn, $descMy ?: null, $descEn ?: null, $harga, $kategori, $categoryId, $fotoUrl, $stok, $urutan]);
                } elseif (menuStationColumnExists()) {
                    $stmt = $pdo->prepare(
                        'INSERT INTO menu_items
                         (shop_id, nama_my, nama_en, deskripsi_my, deskripsi_en, harga, kategori, station_id, foto_url, status_stok, urutan)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$shopId, $namaMy, $namaEn, $descMy ?: null, $descEn ?: null, $harga, $kategori, $stationId, $fotoUrl, $stok, $urutan]);
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO menu_items
                         (shop_id, nama_my, nama_en, deskripsi_my, deskripsi_en, harga, kategori, foto_url, status_stok, urutan)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$shopId, $namaMy, $namaEn, $descMy ?: null, $descEn ?: null, $harga, $kategori, $fotoUrl, $stok, $urutan]);
                }
                $newId = (int) $pdo->lastInsertId();
                if ($canGallery && $newId > 0 && !empty($_FILES['gallery'])) {
                    saveGalleryUploads($pdo, $shopId, $newId, $_FILES['gallery'], $config, $maxGallery);
                }
                if ($newId > 0) {
                    saveMenuAddonsFromForm($pdo, $shopId, $newId, $_POST);
                }
                $flash = 'OK';
            } else {
                if ($id <= 0) {
                    throw new RuntimeException('Invalid ID');
                }
                if ($fotoUrl) {
                    if (menuCategoryColumnExists() && menuStationColumnExists()) {
                        $stmt = $pdo->prepare(
                            'UPDATE menu_items SET
                             nama_my=?, nama_en=?, deskripsi_my=?, deskripsi_en=?, harga=?, kategori=?, menu_category_id=?, station_id=?, foto_url=?, status_stok=?, urutan=?
                             WHERE id=? AND shop_id=?'
                        );
                        $stmt->execute([$namaMy, $namaEn, $descMy ?: null, $descEn ?: null, $harga, $kategori, $categoryId, $stationId, $fotoUrl, $stok, $urutan, $id, $shopId]);
                    } elseif (menuCategoryColumnExists()) {
                        $stmt = $pdo->prepare(
                            'UPDATE menu_items SET
                             nama_my=?, nama_en=?, deskripsi_my=?, deskripsi_en=?, harga=?, kategori=?, menu_category_id=?, foto_url=?, status_stok=?, urutan=?
                             WHERE id=? AND shop_id=?'
                        );
                        $stmt->execute([$namaMy, $namaEn, $descMy ?: null, $descEn ?: null, $harga, $kategori, $categoryId, $fotoUrl, $stok, $urutan, $id, $shopId]);
                    } elseif (menuStationColumnExists()) {
                        $stmt = $pdo->prepare(
                            'UPDATE menu_items SET
                             nama_my=?, nama_en=?, deskripsi_my=?, deskripsi_en=?, harga=?, kategori=?, station_id=?, foto_url=?, status_stok=?, urutan=?
                             WHERE id=? AND shop_id=?'
                        );
                        $stmt->execute([$namaMy, $namaEn, $descMy ?: null, $descEn ?: null, $harga, $kategori, $stationId, $fotoUrl, $stok, $urutan, $id, $shopId]);
                    } else {
                        $stmt = $pdo->prepare(
                            'UPDATE menu_items SET
                             nama_my=?, nama_en=?, deskripsi_my=?, deskripsi_en=?, harga=?, kategori=?, foto_url=?, status_stok=?, urutan=?
                             WHERE id=? AND shop_id=?'
                        );
                        $stmt->execute([$namaMy, $namaEn, $descMy ?: null, $descEn ?: null, $harga, $kategori, $fotoUrl, $stok, $urutan, $id, $shopId]);
                    }
                } else {
                    if (menuCategoryColumnExists() && menuStationColumnExists()) {
                        $stmt = $pdo->prepare(
                            'UPDATE menu_items SET
                             nama_my=?, nama_en=?, deskripsi_my=?, deskripsi_en=?, harga=?, kategori=?, menu_category_id=?, station_id=?, status_stok=?, urutan=?
                             WHERE id=? AND shop_id=?'
                        );
                        $stmt->execute([$namaMy, $namaEn, $descMy ?: null, $descEn ?: null, $harga, $kategori, $categoryId, $stationId, $stok, $urutan, $id, $shopId]);
                    } elseif (menuCategoryColumnExists()) {
                        $stmt = $pdo->prepare(
                            'UPDATE menu_items SET
                             nama_my=?, nama_en=?, deskripsi_my=?, deskripsi_en=?, harga=?, kategori=?, menu_category_id=?, status_stok=?, urutan=?
                             WHERE id=? AND shop_id=?'
                        );
                        $stmt->execute([$namaMy, $namaEn, $descMy ?: null, $descEn ?: null, $harga, $kategori, $categoryId, $stok, $urutan, $id, $shopId]);
                    } elseif (menuStationColumnExists()) {
                        $stmt = $pdo->prepare(
                            'UPDATE menu_items SET
                             nama_my=?, nama_en=?, deskripsi_my=?, deskripsi_en=?, harga=?, kategori=?, station_id=?, status_stok=?, urutan=?
                             WHERE id=? AND shop_id=?'
                        );
                        $stmt->execute([$namaMy, $namaEn, $descMy ?: null, $descEn ?: null, $harga, $kategori, $stationId, $stok, $urutan, $id, $shopId]);
                    } else {
                        $stmt = $pdo->prepare(
                            'UPDATE menu_items SET
                             nama_my=?, nama_en=?, deskripsi_my=?, deskripsi_en=?, harga=?, kategori=?, status_stok=?, urutan=?
                             WHERE id=? AND shop_id=?'
                        );
                        $stmt->execute([$namaMy, $namaEn, $descMy ?: null, $descEn ?: null, $harga, $kategori, $stok, $urutan, $id, $shopId]);
                    }
                }
                if ($canGallery && !empty($_FILES['gallery'])) {
                    saveGalleryUploads($pdo, $shopId, $id, $_FILES['gallery'], $config, $maxGallery);
                }
                saveMenuAddonsFromForm($pdo, $shopId, $id, $_POST);
                $flash = 'OK';
            }
            redirect(baseUrl('admin/owner/menu.php?ok=1'));
        }

        if ($action === 'toggle_stock') {
            $id = (int) ($_POST['id'] ?? 0);
            $pdo->prepare(
                "UPDATE menu_items
                 SET status_stok = IF(status_stok = 'tersedia', 'habis', 'tersedia')
                 WHERE id = ? AND shop_id = ?"
            )->execute([$id, $shopId]);
            redirect(baseUrl('admin/owner/menu.php?ok=1'));
        }

        if ($action === 'delete_photo') {
            if (!$canGallery) {
                throw new RuntimeException(t('gallery_upgrade'));
            }
            $photoId = (int) ($_POST['photo_id'] ?? 0);
            $pdo->prepare('DELETE FROM menu_photos WHERE id = ? AND shop_id = ?')->execute([$photoId, $shopId]);
            $back = (int) ($_POST['id'] ?? 0);
            redirect(baseUrl('admin/owner/menu.php' . ($back > 0 ? ('?edit=' . $back) : '?ok=1')));
        }

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $pdo->prepare('UPDATE menu_items SET is_active = 0 WHERE id = ? AND shop_id = ?')->execute([$id, $shopId]);
            redirect(baseUrl('admin/owner/menu.php?ok=1'));
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$editId = (int) ($_GET['edit'] ?? 0);
$editItem = null;
if ($editId > 0) {
    $s = $pdo->prepare('SELECT * FROM menu_items WHERE id = ? AND shop_id = ? AND is_active = 1 LIMIT 1');
    $s->execute([$editId, $shopId]);
    $editItem = $s->fetch() ?: null;
}

$editPhotos = [];
$editAddons = ['pilihan' => [], 'tambahan' => []];
if ($editItem && $canGallery) {
    $ps = $pdo->prepare('SELECT id, foto_url FROM menu_photos WHERE shop_id = ? AND menu_item_id = ? ORDER BY urutan, id');
    $ps->execute([$shopId, (int) $editItem['id']]);
    $editPhotos = $ps->fetchAll();
}
if ($editItem) {
    foreach (menuAddonsForItem($shopId, (int) $editItem['id'], false) as $addonRow) {
        $jenis = ($addonRow['jenis'] ?? '') === 'pilihan' ? 'pilihan' : 'tambahan';
        $editAddons[$jenis][] = $addonRow;
    }
}

$itemsStmt = $pdo->prepare(
    'SELECT * FROM menu_items WHERE shop_id = ? AND is_active = 1 ORDER BY kategori, urutan, id'
);
$itemsStmt->execute([$shopId]);
$items = $itemsStmt->fetchAll();

if (isset($_GET['ok'])) {
    $flash = 'OK';
}
?>
<?php require dirname(__DIR__, 2) . '/includes/admin_header.php'; ?>
<?php require __DIR__ . '/_nav.php'; ?>

<?php if ($flash): ?>
  <div class="stat-card" style="margin-bottom:16px;border-color:var(--success)"><?= e(currentLang() === 'en' ? 'Saved.' : 'Disimpan.') ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="form-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="order-card" style="margin-bottom:20px">
  <h2 style="margin-top:0"><?= e($editItem ? t('edit') : t('add_menu')) ?></h2>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="<?= $editItem ? 'update' : 'create' ?>">
    <?php if ($editItem): ?>
      <input type="hidden" name="id" value="<?= (int) $editItem['id'] ?>">
    <?php endif; ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px">
      <div class="form-group">
        <label><?= e(t('name_my')) ?></label>
        <input name="nama_my" required value="<?= e($editItem['nama_my'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label><?= e(t('name_en')) ?></label>
        <input name="nama_en" required value="<?= e($editItem['nama_en'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label><?= e(t('price')) ?> (RM)</label>
        <input type="number" step="0.01" min="0" name="harga" required value="<?= e((string) ($editItem['harga'] ?? '')) ?>">
      </div>
      <div class="form-group">
        <label><?= e(t('customer_category')) ?></label>
        <select name="menu_category_id" required>
          <?php
            $editCatId = (int) ($editItem['menu_category_id'] ?? 0);
            foreach ($menuCategories as $cat):
          ?>
            <option value="<?= (int) $cat['id'] ?>" <?= $editCatId === (int) $cat['id'] ? 'selected' : '' ?>><?= e(menuCategoryLabel($cat, $lang)) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="order-meta" style="margin:6px 0 0"><?= e(t('customer_category_hint')) ?></p>
      </div>
      <div class="form-group">
        <label><?= e(t('send_to_station')) ?></label>
        <select name="station_id">
          <?php
            $editSid = (int) ($editItem['station_id'] ?? 0);
            foreach ($stations as $st):
          ?>
            <option value="<?= (int) $st['id'] ?>" <?= $editSid === (int) $st['id'] ? 'selected' : '' ?>><?= e(stationLabel($st, $lang)) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="order-meta" style="margin:6px 0 0"><?= e(t('send_to_station_hint')) ?></p>
      </div>
      <div class="form-group">
        <label><?= e(t('status')) ?></label>
        <select name="status_stok">
          <option value="tersedia" <?= ($editItem['status_stok'] ?? 'tersedia') === 'tersedia' ? 'selected' : '' ?>><?= e(t('stock_available')) ?></option>
          <option value="habis" <?= ($editItem['status_stok'] ?? '') === 'habis' ? 'selected' : '' ?>><?= e(t('stock_out')) ?></option>
        </select>
      </div>
      <div class="form-group">
        <label>Urutan</label>
        <input type="number" name="urutan" value="<?= e((string) ($editItem['urutan'] ?? '0')) ?>">
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label><?= e(t('desc_my')) ?></label>
        <?php if ($canGallery): ?>
          <textarea name="deskripsi_my" rows="4"><?= e($editItem['deskripsi_my'] ?? '') ?></textarea>
        <?php else: ?>
          <input name="deskripsi_my" value="<?= e($editItem['deskripsi_my'] ?? '') ?>">
        <?php endif; ?>
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label><?= e(t('desc_en')) ?></label>
        <?php if ($canGallery): ?>
          <textarea name="deskripsi_en" rows="4"><?= e($editItem['deskripsi_en'] ?? '') ?></textarea>
        <?php else: ?>
          <input name="deskripsi_en" value="<?= e($editItem['deskripsi_en'] ?? '') ?>">
        <?php endif; ?>
      </div>
      <div class="form-group">
        <label><?= e(t('photo')) ?></label>
        <input type="file" name="foto" accept="image/jpeg,image/png,image/webp">
      </div>
      <?php if ($canGallery): ?>
        <div class="form-group" style="grid-column:1/-1">
          <label><?= e(t('gallery_photos')) ?></label>
          <p class="order-meta" style="margin:0 0 8px"><?= e(t('gallery_photos_hint')) ?></p>
          <input type="file" name="gallery[]" accept="image/jpeg,image/png,image/webp" multiple>
          <?php if ($editPhotos): ?>
            <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:12px">
              <?php foreach ($editPhotos as $ph): ?>
                <div style="text-align:center">
                  <img src="<?= e(uploadUrl($ph['foto_url'])) ?>" alt="" style="width:72px;height:72px;object-fit:cover;border-radius:8px;display:block">
                  <button type="submit" form="delete-photo-<?= (int) $ph['id'] ?>" class="btn btn-ghost btn-sm" style="color:var(--danger);margin-top:4px"><?= e(t('delete')) ?></button>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <p class="order-meta" style="grid-column:1/-1"><?= e(t('gallery_upgrade')) ?></p>
      <?php endif; ?>

      <div class="form-group" style="grid-column:1/-1;border-top:1px solid var(--border);padding-top:16px;margin-top:4px">
        <h3 style="margin:0 0 6px;font-size:1rem"><?= e(t('menu_addons_pilihan')) ?></h3>
        <p class="order-meta" style="margin:0 0 10px"><?= e(t('menu_addons_pilihan_hint')) ?></p>
        <div class="addon-rows" id="addon-pilihan-rows">
          <?php
            $pilihanRows = $editAddons['pilihan'] ?: [['nama_my' => '', 'nama_en' => '', 'harga_delta' => '']];
            foreach ($pilihanRows as $row):
          ?>
            <div class="addon-row" style="display:grid;grid-template-columns:1fr 1fr 120px 40px;gap:8px;margin-bottom:8px;align-items:end">
              <input name="addon_pilihan_my[]" placeholder="<?= e(t('name_my')) ?>" value="<?= e((string) ($row['nama_my'] ?? '')) ?>">
              <input name="addon_pilihan_en[]" placeholder="<?= e(t('name_en')) ?>" value="<?= e((string) ($row['nama_en'] ?? '')) ?>">
              <input type="number" step="0.01" min="0" name="addon_pilihan_harga[]" placeholder="+RM" value="<?= e((string) ($row['harga_delta'] ?? '')) ?>">
              <button type="button" class="btn btn-ghost btn-sm addon-row-remove" aria-label="<?= e(t('delete')) ?>">×</button>
            </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-secondary btn-sm" id="btn-add-pilihan"><?= e(t('menu_addons_add_row')) ?></button>
      </div>

      <div class="form-group" style="grid-column:1/-1">
        <h3 style="margin:0 0 6px;font-size:1rem"><?= e(t('menu_addons_tambahan')) ?></h3>
        <p class="order-meta" style="margin:0 0 10px"><?= e(t('menu_addons_tambahan_hint')) ?></p>
        <div class="addon-rows" id="addon-tambahan-rows">
          <?php
            $tambahanRows = $editAddons['tambahan'] ?: [['nama_my' => '', 'nama_en' => '', 'harga_delta' => '']];
            foreach ($tambahanRows as $row):
          ?>
            <div class="addon-row" style="display:grid;grid-template-columns:1fr 1fr 120px 40px;gap:8px;margin-bottom:8px;align-items:end">
              <input name="addon_tambahan_my[]" placeholder="<?= e(t('name_my')) ?>" value="<?= e((string) ($row['nama_my'] ?? '')) ?>">
              <input name="addon_tambahan_en[]" placeholder="<?= e(t('name_en')) ?>" value="<?= e((string) ($row['nama_en'] ?? '')) ?>">
              <input type="number" step="0.01" min="0" name="addon_tambahan_harga[]" placeholder="+RM" value="<?= e((string) ($row['harga_delta'] ?? '')) ?>">
              <button type="button" class="btn btn-ghost btn-sm addon-row-remove" aria-label="<?= e(t('delete')) ?>">×</button>
            </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-secondary btn-sm" id="btn-add-tambahan"><?= e(t('menu_addons_add_row')) ?></button>
      </div>
    </div>
    <div style="display:flex;gap:8px;margin-top:8px">
      <button type="submit" class="btn btn-primary"><?= e(t('save')) ?></button>
      <?php if ($editItem): ?>
        <a class="btn btn-secondary" href="<?= e(baseUrl('admin/owner/menu.php')) ?>"><?= e(t('cancel')) ?></a>
      <?php endif; ?>
    </div>
  </form>
  <?php foreach ($editPhotos as $ph): ?>
    <form id="delete-photo-<?= (int) $ph['id'] ?>" method="post" style="display:none">
      <input type="hidden" name="action" value="delete_photo">
      <input type="hidden" name="photo_id" value="<?= (int) $ph['id'] ?>">
      <input type="hidden" name="id" value="<?= (int) ($editItem['id'] ?? 0) ?>">
    </form>
  <?php endforeach; ?>
</div>

<div class="table-list-wrap">
  <div class="owner-menu-toolbar">
    <h2 style="margin:0;font-size:1.05rem"><?= e(t('manage_menu')) ?></h2>
    <label class="owner-menu-search" for="owner-menu-search">
      <span class="sr-only"><?= e(t('search')) ?></span>
      <input type="search" id="owner-menu-search" placeholder="<?= e(t('menu_search_ph')) ?>" autocomplete="off" enterkeyhint="search">
      <button type="button" class="owner-menu-search-clear" id="owner-menu-search-clear" aria-label="<?= e(t('close')) ?>" hidden>&times;</button>
    </label>
  </div>
  <p class="owner-menu-search-meta" id="owner-menu-search-meta"></p>
  <p class="owner-menu-search-empty" id="owner-menu-search-empty" hidden><?= e(t('menu_search_empty')) ?></p>
  <table class="table-list" id="owner-menu-table">
    <thead>
      <tr>
        <th><?= e(t('photo')) ?></th>
        <th><?= e(t('name_my')) ?></th>
        <th><?= e(t('customer_category')) ?></th>
        <th><?= e(t('send_to_station')) ?></th>
        <th><?= e(t('price')) ?></th>
        <th><?= e(t('status')) ?></th>
        <th><?= e(t('actions')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $item):
        $cid = (int) ($item['menu_category_id'] ?? 0);
        $cname = t($item['kategori']);
        foreach ($menuCategories as $cat) {
            if ((int) $cat['id'] === $cid) {
                $cname = menuCategoryLabel($cat, $lang);
                break;
            }
        }
        $sid = (int) ($item['station_id'] ?? 0);
        $sname = '';
        foreach ($stations as $st) {
            if ((int) $st['id'] === $sid) {
                $sname = stationLabel($st, $lang);
                break;
            }
        }
        $searchText = mb_strtolower(
            ($item['nama_my'] ?? '') . ' '
            . ($item['nama_en'] ?? '') . ' '
            . $cname . ' '
            . $sname . ' '
            . formatMoney((float) $item['harga'])
        );
      ?>
        <tr data-search="<?= e($searchText) ?>">
          <td>
            <?php if ($item['foto_url']): ?>
              <img src="<?= e(uploadUrl($item['foto_url'])) ?>" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:8px">
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
          <td>
            <strong><?= e($item['nama_my']) ?></strong><br>
            <span class="order-meta"><?= e($item['nama_en']) ?></span>
          </td>
          <td><?php echo e($cname); ?></td>
          <td><?php echo e($sname !== '' ? $sname : '—'); ?></td>
          <td><?= e(formatMoney($item['harga'])) ?></td>
          <td>
            <span class="badge <?= $item['status_stok'] === 'tersedia' ? 'badge-selesai' : 'badge-belum_bayar' ?>">
              <?= e($item['status_stok'] === 'tersedia' ? t('stock_available') : t('stock_out')) ?>
            </span>
          </td>
          <td style="white-space:nowrap">
            <a class="btn btn-secondary btn-sm" href="?edit=<?= (int) $item['id'] ?>"><?= e(t('edit')) ?></a>
            <form method="post" style="display:inline">
              <input type="hidden" name="action" value="toggle_stock">
              <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
              <button type="submit" class="btn btn-ghost btn-sm"><?= e(t('stock_out')) ?> ⇄</button>
            </form>
            <form method="post" style="display:inline" onsubmit="return confirm('Padam?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
              <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)"><?= e(t('delete')) ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
(function () {
  function bindRemove(container) {
    container.querySelectorAll('.addon-row-remove').forEach(function (btn) {
      if (btn.dataset.bound) return;
      btn.dataset.bound = '1';
      btn.addEventListener('click', function () {
        const row = btn.closest('.addon-row');
        const rows = container.querySelectorAll('.addon-row');
        if (rows.length <= 1) {
          row.querySelectorAll('input').forEach(function (inp) { inp.value = ''; });
          return;
        }
        row.remove();
      });
    });
  }
  function addRow(containerId, prefix) {
    const container = document.getElementById(containerId);
    if (!container) return;
    const sample = container.querySelector('.addon-row');
    if (!sample) return;
    const clone = sample.cloneNode(true);
    clone.querySelectorAll('input').forEach(function (inp) { inp.value = ''; });
    container.appendChild(clone);
    bindRemove(container);
  }
  document.getElementById('btn-add-pilihan')?.addEventListener('click', function () {
    addRow('addon-pilihan-rows', 'pilihan');
  });
  document.getElementById('btn-add-tambahan')?.addEventListener('click', function () {
    addRow('addon-tambahan-rows', 'tambahan');
  });
  bindRemove(document.getElementById('addon-pilihan-rows'));
  bindRemove(document.getElementById('addon-tambahan-rows'));

  const menuSearch = document.getElementById('owner-menu-search');
  const menuSearchClear = document.getElementById('owner-menu-search-clear');
  const menuSearchEmpty = document.getElementById('owner-menu-search-empty');
  const menuSearchMeta = document.getElementById('owner-menu-search-meta');
  const menuRows = Array.from(document.querySelectorAll('#owner-menu-table tbody tr[data-search]'));
  const totalRows = menuRows.length;

  function normalizeOwnerSearch(value) {
    return (value || '').trim().toLowerCase().replace(/\s+/g, ' ');
  }

  function applyOwnerMenuSearch() {
    const q = normalizeOwnerSearch(menuSearch?.value);
    const isSearching = q.length > 0;
    let visible = 0;
    menuRows.forEach(function (row) {
      const text = normalizeOwnerSearch(row.getAttribute('data-search') || '');
      const show = !isSearching || text.indexOf(q) !== -1;
      row.hidden = !show;
      if (show) visible++;
    });
    if (menuSearchEmpty) menuSearchEmpty.hidden = !isSearching || visible > 0;
    if (menuSearchClear) menuSearchClear.hidden = !isSearching;
    if (menuSearchMeta) {
      if (isSearching) {
        menuSearchMeta.textContent = visible + ' / ' + totalRows;
      } else {
        menuSearchMeta.textContent = totalRows > 0 ? String(totalRows) : '';
      }
    }
  }

  menuSearch?.addEventListener('input', applyOwnerMenuSearch);
  menuSearchClear?.addEventListener('click', function () {
    if (menuSearch) menuSearch.value = '';
    applyOwnerMenuSearch();
    menuSearch?.focus();
  });
  applyOwnerMenuSearch();
})();
</script>

<?php require dirname(__DIR__, 2) . '/includes/admin_footer.php'; ?>
