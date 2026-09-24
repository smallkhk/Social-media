<?php
declare(strict_types=1);

/**
 * Send a plain-text email with PHP mail() (works on Namecheap/cPanel hosting).
 * MAIL_FROM should be a mailbox on your own domain (cPanel > Email Accounts), or mail may land in spam.
 */
function send_mail(string $to, string $subject, string $body): bool
{
    $from = defined('MAIL_FROM') && MAIL_FROM !== '' ? MAIL_FROM : 'no-reply@' . (parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost');
    $headers = [
        'From' => SITE_NAME . ' <' . $from . '>',
        'Reply-To' => $from,
        'Content-Type' => 'text/plain; charset=UTF-8',
        'X-Mailer' => 'NoraPanel',
    ];
    $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $ok = @mail($to, $subject, $body, $headers, '-f' . $from);
    if (!$ok) {
        error_log("send_mail to $to failed");
    }
    return $ok;
}

function send_password_reset(array $user): void
{
    q('DELETE FROM password_resets WHERE user_id = ? OR expires_at < NOW()', [$user['id']]);
    $token = bin2hex(random_bytes(32));
    q('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL 1 HOUR)',
        [$user['id'], hash('sha256', $token)]);
    $link = url('reset-password.php?token=' . $token);
    send_mail($user['email'], 'Reset your ' . SITE_NAME . ' password',
        "Hi {$user['username']},\n\nSomeone asked to reset the password of your " . SITE_NAME . " account.\n"
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
