<?php
declare(strict_types=1);

/**
 * Send a plain-text email. Uses the SMTP server from Admin > Settings when one is set,
 * otherwise PHP mail() (works on Namecheap/cPanel hosting without any setup).
 * The sender should be a mailbox on your own domain, or mail may land in spam.
 */
function send_mail(string $to, string $subject, string $body): bool
{
    $error = mail_send($to, $subject, $body);
    if ($error !== null) {
        error_log("send_mail to $to failed: $error");
    }
    return $error === null;
}

/** @return string|null error message, or null when the mail was accepted */
function mail_send(string $to, string $subject, string $body): ?string
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return 'Invalid recipient address';
    }
    $from = (string)cfg('MAIL_FROM') !== '' ? (string)cfg('MAIL_FROM') : 'no-reply@' . (parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost');
    $from = str_replace(["\r", "\n"], '', $from);
    $site = str_replace(["\r", "\n"], '', (string)cfg('SITE_NAME'));
    $name = match (true) {
        (bool)preg_match('/^[A-Za-z0-9 !#$%&\'*+\/=?^_`{|}~-]+$/', $site) => $site,
        (bool)preg_match('/^[\x20-\x7E]+$/', $site) => '"' . addcslashes($site, '"\\') . '"',
        default => '=?UTF-8?B?' . base64_encode($site) . '?=',
    };
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    if ((string)cfg('SMTP_HOST') !== '') {
        return smtp_send($to, $encodedSubject, $body, $from, $name);
    }
    $headers = [
        'From' => "$name <$from>",
        'Reply-To' => $from,
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'X-Mailer' => 'NoraPanel',
    ];
    return @mail($to, $encodedSubject, $body, $headers, '-f' . $from) ? null : 'PHP mail() failed';
}

/** Minimal SMTP client: SSL (port 465), STARTTLS (587) or plain, with AUTH LOGIN. */
function smtp_send(string $to, string $subject, string $body, string $from, string $name): ?string
{
    $secure = (string)cfg('SMTP_SECURE');
    $host = (string)cfg('SMTP_HOST');
    $port = (int)cfg('SMTP_PORT') ?: ($secure === 'ssl' ? 465 : 587);
    $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
    $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . "$host:$port", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
    if (!$fp) {
        return "Could not connect to $host:$port ($errstr)";
    }
    stream_set_timeout($fp, 20);

    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $cmd = function (?string $command, array $expect) use ($fp, $read): string {
        if ($command !== null) {
            fwrite($fp, $command . "\r\n");
        }
        $reply = $read();
        if (!in_array((int)substr($reply, 0, 3), $expect, true)) {
            throw new RuntimeException(trim($reply) !== '' ? trim($reply) : 'No reply from server');
        }
        return $reply;
    };

    try {
        $cmd(null, [220]);
        $helo = 'EHLO ' . (parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost');
        $cmd($helo, [250]);
        if ($secure === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS encryption failed');
            }
            $cmd($helo, [250]);
        }
        if ((string)cfg('SMTP_USER') !== '') {
            $cmd('AUTH LOGIN', [334]);
            $cmd(base64_encode((string)cfg('SMTP_USER')), [334]);
            $cmd(base64_encode((string)cfg('SMTP_PASS')), [235]);
        }
        $cmd("MAIL FROM:<$from>", [250]);
        $cmd("RCPT TO:<$to>", [250, 251]);
        $cmd('DATA', [354]);
        $domain = substr(strrchr($from, '@') ?: '@localhost', 1);
        $message = implode("\r\n", [
            'Date: ' . date('r'),
            "From: $name <$from>",
            "To: <$to>",
            "Subject: $subject",
            'Message-ID: <' . bin2hex(random_bytes(12)) . "@$domain>",
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            rtrim(chunk_split(base64_encode($body), 76, "\r\n")),
        ]);
        $cmd($message . "\r\n.", [250]);
        try {
            $cmd('QUIT', [221]);
        } catch (RuntimeException $e) {
            // mail already accepted
        }
        fclose($fp);
        return null;
    } catch (RuntimeException $e) {
        fclose($fp);
        return 'SMTP: ' . $e->getMessage();
    }
}

function send_password_reset(array $user): void
{
    q('DELETE FROM password_resets WHERE user_id = ? OR expires_at < NOW()', [$user['id']]);
    $token = bin2hex(random_bytes(32));
    q('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL 1 HOUR)',
        [$user['id'], hash('sha256', $token)]);
    $link = url('reset-password.php?token=' . $token);
    send_mail($user['email'], 'Reset your ' . cfg('SITE_NAME') . ' password',
        "Hi {$user['username']},\n\nSomeone asked to reset the password of your " . cfg('SITE_NAME') . " account.\n"
        . "Open this link within 1 hour to choose a new password:\n\n$link\n\n"
        . "If it wasn't you, ignore this email - your password stays the same.\n");
}

/** @return array|null the user the (unexpired) token belongs to */
function password_reset_user(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    return row("SELECT u.* FROM password_resets r JOIN users u ON u.id = r.user_id
                WHERE r.token_hash = ? AND r.expires_at > NOW() AND u.status = 'active'", [hash('sha256', $token)]);
}
