<?php
/**
 * Reusable QR card for owner admin.
 * Expects: $qrTitle, $qrHint, $qrUrl, optional $qrBadge, $qrActions (HTML), $qrId
 */
$qrTitle = $qrTitle ?? '';
$qrHint = $qrHint ?? '';
$qrUrl = $qrUrl ?? '';
$qrBadge = $qrBadge ?? '';
$qrId = $qrId ?? ('qr-' . bin2hex(random_bytes(3)));
$qrImg = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=8&data=' . urlencode($qrUrl);
$qrDownload = 'https://api.qrserver.com/v1/create-qr-code/?size=600x600&margin=8&data=' . urlencode($qrUrl);
?>
<article class="qr-card order-card">
  <div class="qr-card-head">
    <div>
      <h3 class="qr-card-title"><?= e($qrTitle) ?></h3>
      <?php if ($qrBadge !== ''): ?>
        <span class="badge badge-selesai"><?= e($qrBadge) ?></span>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($qrHint !== ''): ?>
    <p class="order-meta qr-card-hint"><?= e($qrHint) ?></p>
  <?php endif; ?>
  <div class="qr-card-visual">
    <img src="<?= e($qrImg) ?>" alt="QR" width="200" height="200" loading="lazy">
  </div>
  <div class="qr-url-wrap">
    <input type="text" class="qr-url-input" id="<?= e($qrId) ?>" readonly value="<?= e($qrUrl) ?>" aria-label="URL">
    <button type="button" class="btn btn-secondary btn-sm qr-copy-btn" data-copy-target="<?= e($qrId) ?>"><?= e(t('cafe_copy_link')) ?></button>
  </div>
  <div class="qr-card-actions">
    <a class="btn btn-primary btn-sm" href="<?= e($qrDownload) ?>" download="tabletap-qr.png" target="_blank" rel="noopener"><?= e(t('download_qr')) ?></a>
    <?php if (!empty($qrActions)): ?>
      <?= $qrActions ?>
    <?php endif; ?>
  </div>
</article>
