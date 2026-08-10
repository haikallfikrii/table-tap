<?php
declare(strict_types=1);
?>
</div>
<script src="<?= e(assetUrl('js/i18n.js')) ?>"></script>
<?php if (!empty($adminScripts)): ?>
  <?php foreach ($adminScripts as $src): ?>
    <script src="<?= e($src) ?>"></script>
  <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
