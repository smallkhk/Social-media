<?php
declare(strict_types=1);

/**
 * Settings editable in Admin > Settings. Values saved there (settings table) override the
 * defaults in app/config.php; keys without a config constant fall back to these defaults.
 */
const SETTING_DEFAULTS = [
    'SMTP_HOST' => '',
    'SMTP_PORT' => 465,
    'SMTP_SECURE' => 'ssl',
    'SMTP_USER' => '',
    'SMTP_PASS' => '',
    'SUPPORT_EMAIL' => '',
    'SUPPORT_WHATSAPP' => '',
    'SUPPORT_TELEGRAM' => '',
    'SUPPORT_NOTE' => '',
];

const SCHEMA_VERSION = 4;

/** Tables added after the first release; created automatically on existing installs. */
function migrate_schema(): void
{
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        name VARCHAR(64) PRIMARY KEY,
        value TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS tickets (
        id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        subject VARCHAR(190) NOT NULL,
        order_id INT UNSIGNED NULL,
        status ENUM('open', 'answered', 'closed') NOT NULL DEFAULT 'open',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_tickets_user (user_id),
        KEY idx_tickets_status (status),
        CONSTRAINT fk_tickets_user FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_messages (
        id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
        ticket_id INT UNSIGNED NOT NULL,
        from_admin TINYINT(1) NOT NULL DEFAULT 0,
        message TEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_ticket_messages_ticket (ticket_id),
        CONSTRAINT fk_ticket_messages_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->prepare('REPLACE INTO settings (name, value) VALUES (?, ?)')->execute(['schema_version', (string)SCHEMA_VERSION]);
}

function settings_all(bool $reload = false): array
{
    static $cache = null;
    if ($cache === null || $reload) {
        try {
            $cache = array_column(all('SELECT name, value FROM settings'), 'value', 'name');
        } catch (PDOException $e) {
            $cache = [];
        }
        if ((int)($cache['schema_version'] ?? 0) < SCHEMA_VERSION) {
            migrate_schema();
            $cache = array_column(all('SELECT name, value FROM settings'), 'value', 'name');
        }
    }
    return $cache;
}

/** Current value of a setting, typed like its default (bool / number / string). */
function cfg(string $key)
{
    $default = defined($key) ? constant($key) : (SETTING_DEFAULTS[$key] ?? null);
    $saved = settings_all()[$key] ?? null;
    if ($saved === null) {
        return $default;
    }
    return match (true) {
        is_bool($default) => $saved === '1',
        $key === 'SMTP_PORT' => (int)$saved,
        is_int($default), is_float($default) => (float)$saved,
        default => $saved,
    };
}

function save_settings(array $values): void
{
    $stmt = db()->prepare('REPLACE INTO settings (name, value) VALUES (?, ?)');
    foreach ($values as $key => $value) {
        $stmt->execute([$key, is_bool($value) ? ($value ? '1' : '0') : (string)$value]);
    }
    settings_all(true);
}
