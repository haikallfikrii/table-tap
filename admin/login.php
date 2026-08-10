<?php
/**
 * Admin login
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/i18n.php';

startAppSession();
$user = currentUser();
if ($user) {
    redirect(roleHome($user['role']));
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($username === '' || $password === '') {
        $error = t('error_generic');
    } else {
        try {
            if (loginUser($username, $password)) {
                $u = currentUser();
                redirect(roleHome($u['role']));
            }
            $error = currentLang() === 'en'
                ? 'Invalid username or password.'
                : 'Nama pengguna atau kata laluan salah.';
        } catch (PDOException $e) {
            $error = currentLang() === 'en'
                ? 'Cannot connect to database. On Hostinger set db_host to localhost in config/config.php.'
                : 'Gagal sambung database. Di Hostinger, db_host dalam config/config.php biasanya mesti localhost.';
        } catch (Throwable $e) {
            $error = currentLang() === 'en'
                ? 'Temporary system error. Please try again.'
                : 'Ralat sistem sementara. Sila cuba lagi.';
        }
    }
}

$lang = currentLang();
$config = getConfig();
?>
<!DOCTYPE html>
<html lang="<?= e($lang === 'en' ? 'en' : 'ms') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('login')) ?> — <?= e($config['app_name']) ?></title>
  <link rel="stylesheet" href="<?= e(assetUrl('css/admin.css')) ?>">
</head>
<body class="admin">
  <div class="login-page">
    <div class="login-card">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px">
        <div>
          <h1><?= e($config['app_name']) ?></h1>
          <p class="sub"><?= e(t('login')) ?></p>
        </div>
        <div class="lang-toggle">
          <button type="button" data-set-lang="my" class="<?= $lang === 'my' ? 'active' : '' ?>"><?= e(t('lang_my')) ?></button>
          <button type="button" data-set-lang="en" class="<?= $lang === 'en' ? 'active' : '' ?>"><?= e(t('lang_en')) ?></button>
        </div>
      </div>

      <?php if ($error): ?>
        <div class="form-error"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" autocomplete="username">
        <div class="form-group">
          <label for="username"><?= e(t('username')) ?></label>
          <input type="text" id="username" name="username" required autofocus
                 value="<?= e($_POST['username'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="password"><?= e(t('password')) ?></label>
          <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%"><?= e(t('login')) ?></button>
      </form>
    </div>
  </div>
  <script src="<?= e(assetUrl('js/i18n.js')) ?>"></script>
</body>
</html>
