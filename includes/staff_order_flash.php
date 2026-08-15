<?php
$placedId = (int) ($_GET['ordered'] ?? 0);
$placedMeja = trim((string) ($_GET['meja'] ?? ''));
if ($placedId > 0):
?>
<div class="stat-card" style="margin-bottom:16px;border-color:var(--success)">
  <?= e(t('staff_order_ok', (string) $placedId, $placedMeja !== '' ? $placedMeja : '—')) ?>
</div>
<?php endif; ?>
