<?php
/**
 * mindpool – Konfiguration
 *
 * Passwort ändern:
 *   php -r "echo password_hash('NeuesPasswort', PASSWORD_BCRYPT) . PHP_EOL;"
 * Den ausgegebenen Hash unten bei AUTH_HASH eintragen.
 */

// Passwort-Hash (bcrypt) – Standard-Passwort: "mindscool2025"
define('AUTH_HASH', '$2y$12$bFhEc3Udcrgt00lFV00djuK1q2wYAOp3ah25br79kQeNUjKg605pC');

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
