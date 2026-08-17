<?php
/**
 * Simple mail helper — PHP mail() or SMTP from config.
 */

declare(strict_types=1);

function appMailFrom(): string
{
    $c = getConfig();
    $from = trim((string) ($c['mail_from'] ?? ''));
    if ($from !== '') {
        return $from;
    }
    $host = requestHost();
    if ($host === '' || $host === 'localhost' || $host === '127.0.0.1') {
        return 'noreply@tabletap.my';
    }
    return 'noreply@' . $host;
}

function sendAppMail(string $to, string $subject, string $bodyText): bool
{
    $c = getConfig();
    $smtpHost = trim((string) ($c['smtp_host'] ?? ''));
    if ($smtpHost !== '') {
        return sendAppMailSmtp($to, $subject, $bodyText);
    }

    $from = appMailFrom();
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . $from,
        'Reply-To: ' . $from,
    ];
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $bodyText, implode("\r\n", $headers));
}

function sendAppMailSmtp(string $to, string $subject, string $bodyText): bool
{
    $c = getConfig();
    $host = (string) ($c['smtp_host'] ?? '');
    $port = (int) ($c['smtp_port'] ?? 587);
    $user = (string) ($c['smtp_user'] ?? '');
    $pass = (string) ($c['smtp_pass'] ?? '');
    $from = appMailFrom();

    $errno = 0;
    $errstr = '';
    $socket = @fsockopen($host, $port, $errno, $errstr, 15);
    if (!$socket) {
        return false;
    }

    $read = static function () use ($socket): string {
        $data = '';
        while ($line = fgets($socket, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };

    $write = static function (string $cmd) use ($socket): void {
        fwrite($socket, $cmd . "\r\n");
    };

    $read();
    $write('EHLO tabletap');
    $read();

    if ($port === 587) {
        $write('STARTTLS');
        $resp = $read();
        if (strpos($resp, '220') === false) {
            fclose($socket);
            return false;
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return false;
        }
        $write('EHLO tabletap');
        $read();
    }

    if ($user !== '') {
        $write('AUTH LOGIN');
        $read();
        $write(base64_encode($user));
        $read();
        $write(base64_encode($pass));
        $authResp = $read();
        if (strpos($authResp, '235') === false) {
            fclose($socket);
            return false;
        }
    }

    $write('MAIL FROM:<' . $from . '>');
    $read();
    $write('RCPT TO:<' . $to . '>');
    $read();
    $write('DATA');
    $read();

    $msg = "From: {$from}\r\n";
    $msg .= "To: {$to}\r\n";
    $msg .= 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
    $msg .= "MIME-Version: 1.0\r\n";
    $msg .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $msg .= $bodyText . "\r\n.";
    $write($msg);
    $dataResp = $read();
    $write('QUIT');
    fclose($socket);

    return strpos($dataResp, '250') !== false;
}
