<?php
/**
 * Email OTP verification — anti-spam for cafe mode.
 */

declare(strict_types=1);

require_once __DIR__ . '/mail.php';

function securityPepper(): string
{
    $c = getConfig();
    $pepper = trim((string) ($c['security_pepper'] ?? ''));
    if ($pepper === '') {
        $pepper = hash('sha256', (string) ($c['cron_secret'] ?? 'tabletap'));
    }
    return $pepper;
}

function hashContact(string $value): string
{
    return hash('sha256', strtolower(trim($value)) . '|' . securityPepper());
}

function hashClientIp(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    return hash('sha256', $ip . '|' . securityPepper());
}

function normalizeEmail(string $email): ?string
{
    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    if (strlen($email) > 255) {
        return null;
    }
    return $email;
}

function verificationTableExists(): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    try {
        $exists = (bool) db()->query("SHOW TABLES LIKE 'verification_codes'")->fetch();
    } catch (Throwable $e) {
        $exists = false;
    }
    return $exists;
}

function generateOtpCode(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function assertOtpSendRateLimit(int $shopId, string $destinationHash): void
{
    if (!verificationTableExists()) {
        return;
    }
    $pdo = db();
    $ipHash = hashClientIp();

    $emailStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM verification_codes
         WHERE shop_id = ? AND destination_hash = ?
           AND consumed_at IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
    );
    $emailStmt->execute([$shopId, $destinationHash]);
    if ((int) $emailStmt->fetchColumn() >= 5) {
        jsonError(t('cafe_otp_blocked'), 429);
    }

    $ipStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM verification_codes
         WHERE shop_id = ? AND ip_hash = ?
           AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
    );
    $ipStmt->execute([$shopId, $ipHash]);
    if ((int) $ipStmt->fetchColumn() >= 20) {
        jsonError(t('cafe_otp_blocked'), 429);
    }
}

/**
 * Send 6-digit OTP to email. Returns pending token for verify step.
 *
 * @return array{pending_token:string,expires_in:int}
 */
function sendEmailOtp(int $shopId, string $email, string $lang, string $shopName): array
{
    if (!verificationTableExists()) {
        jsonError('Verification unavailable', 503);
    }

    $email = normalizeEmail($email);
    if ($email === null) {
        jsonError(t('cafe_email_invalid'), 400);
    }

    $destHash = hashContact($email);
    assertOtpSendRateLimit($shopId, $destHash);

    $code = generateOtpCode();
    $codeHash = password_hash($code, PASSWORD_DEFAULT);
    $expiresAt = date('Y-m-d H:i:s', time() + 300);

    db()->prepare(
        'INSERT INTO verification_codes
         (shop_id, channel, destination_hash, code_hash, expires_at, ip_hash)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$shopId, 'email', $destHash, $codeHash, $expiresAt, hashClientIp()]);

    $subject = $lang === 'en'
        ? "{$shopName} — your order code"
        : "{$shopName} — kod pesanan anda";
    $body = $lang === 'en'
        ? "Your TableTap verification code is: {$code}\n\nValid for 5 minutes. Do not share this code.\n\n— {$shopName}"
        : "Kod pengesahan TableTap anda: {$code}\n\nSah 5 minit. Jangan kongsi kod ini.\n\n— {$shopName}";

    if (!sendAppMail($email, $subject, $body)) {
        jsonError(t('cafe_otp_send_failed'), 502);
    }

    return [
        'email_masked' => maskEmail($email),
        'expires_in' => 300,
    ];
}

function maskEmail(string $email): string
{
    $parts = explode('@', $email, 2);
    if (count($parts) !== 2) {
        return '***';
    }
    $local = $parts[0];
    $domain = $parts[1];
    if (strlen($local) <= 2) {
        $masked = str_repeat('*', strlen($local));
    } else {
        $masked = substr($local, 0, 2) . str_repeat('*', max(1, strlen($local) - 2));
    }
    return $masked . '@' . $domain;
}

/**
 * Verify OTP and return destination hash on success.
 */
function verifyEmailOtp(int $shopId, string $email, string $code): ?string
{
    if (!verificationTableExists()) {
        return null;
    }

    $email = normalizeEmail($email);
    if ($email === null || !preg_match('/^\d{6}$/', trim($code))) {
        return null;
    }

    $destHash = hashContact($email);
    $stmt = db()->prepare(
        "SELECT id, code_hash, attempts, expires_at
         FROM verification_codes
         WHERE shop_id = ? AND destination_hash = ? AND channel = 'email'
           AND consumed_at IS NULL AND expires_at > NOW()
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->execute([$shopId, $destHash]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    if ((int) $row['attempts'] >= 5) {
        return null;
    }

    $attempts = (int) $row['attempts'] + 1;
    db()->prepare('UPDATE verification_codes SET attempts = ? WHERE id = ?')
        ->execute([$attempts, (int) $row['id']]);

    if (!password_verify(trim($code), (string) $row['code_hash'])) {
        return null;
    }

    db()->prepare('UPDATE verification_codes SET consumed_at = ? WHERE id = ?')
        ->execute([appNow(), (int) $row['id']]);

    return $destHash;
}
