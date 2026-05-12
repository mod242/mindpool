<?php
/**
 * mindpool – Konfiguration
 *
 * Passwort ändern:
 *   php -r "echo password_hash('NeuesPasswort', PASSWORD_BCRYPT) . PHP_EOL;"
 * Den ausgegebenen Hash in config.local.php oder MINDPOOL_AUTH_HASH setzen.
 */

// Lokale, nicht versionierte Overrides laden (z.B. AUTH_HASH).
$localConfig = __DIR__ . '/config.local.php';
if (file_exists($localConfig)) {
    require_once $localConfig;
}

// Passwort-Hash (bcrypt).
if (!defined('AUTH_HASH')) {
    define('AUTH_HASH', getenv('MINDPOOL_AUTH_HASH') ?: '');
}

// Session-Timeout in Sekunden (2 Stunden)
define('SESSION_TIMEOUT', 7200);

// Datenverzeichnis
define('DATA_DIR', __DIR__ . '/data/');

// Maximale Anzahl Login-Versuche bevor Sperre
define('MAX_LOGIN_ATTEMPTS', 5);

// Sperrdauer in Sekunden (15 Minuten)
define('LOCKOUT_DURATION', 900);

// Maximale Foto-Größe in Bytes (500 KB)
define('MAX_FOTO_SIZE', 512000);
