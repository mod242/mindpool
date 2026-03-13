<?php
/**
 * mindpool – Authentifizierung und Session-Verwaltung
 */

require_once __DIR__ . '/config.php';

/**
 * Session starten mit sicheren Einstellungen
 */
function init_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => SESSION_TIMEOUT,
            'path'     => '/',
            'httponly'  => true,
            'samesite'  => 'Strict'
        ]);
        session_start();
    }
}

/**
 * Prüft ob der Benutzer eingeloggt ist und die Session noch gültig ist
 */
function is_authenticated(): bool {
    init_session();

    if (empty($_SESSION['authenticated']) || empty($_SESSION['last_activity'])) {
        return false;
    }

    // Session-Timeout prüfen
    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        session_destroy();
        return false;
    }

    // Aktivitätszeitpunkt aktualisieren
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Erzwingt Authentifizierung – leitet auf Login um wenn nicht eingeloggt
 */
function require_auth() {
    if (!is_authenticated()) {
        header('Location: index.php');
        exit;
    }
}

/**
 * Login durchführen
 */
function login(string $password): bool {
    $ip = get_client_ip();

    // Rate-Limiting prüfen
    if (is_locked_out($ip)) {
        return false;
    }

    if (password_verify($password, AUTH_HASH)) {
        init_session();
        // Session-Fixation-Schutz
        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
        $_SESSION['last_activity'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        // Fehlversuche zurücksetzen
        reset_login_attempts($ip);
        return true;
    }

    // Fehlversuch registrieren
    record_failed_attempt($ip);
    return false;
}

/**
 * Logout durchführen
 */
function logout() {
    init_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

/**
 * CSRF-Token generieren oder zurückgeben
 */
function get_csrf_token(): string {
    init_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF-Token validieren
 */
function validate_csrf(string $token): bool {
    return hash_equals(get_csrf_token(), $token);
}

/**
 * CSRF-Token aus Request prüfen – beendet bei Fehler
 */
function require_csrf() {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validate_csrf($token)) {
        http_response_code(403);
        echo json_encode(['error' => 'Ungültiges CSRF-Token']);
        exit;
    }
}

/**
 * Client-IP ermitteln
 */
function get_client_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Prüft ob eine IP gesperrt ist
 */
function is_locked_out(string $ip): bool {
    $attempts = load_login_attempts();
    if (!isset($attempts[$ip])) {
        return false;
    }

    $record = $attempts[$ip];
    if ($record['count'] >= MAX_LOGIN_ATTEMPTS) {
        // Sperre noch aktiv?
        if (time() - $record['last_attempt'] < LOCKOUT_DURATION) {
            return true;
        }
        // Sperre abgelaufen – zurücksetzen
        reset_login_attempts($ip);
    }

    return false;
}

/**
 * Fehlversuch protokollieren
 */
function record_failed_attempt(string $ip) {
    $attempts = load_login_attempts();

    if (!isset($attempts[$ip])) {
        $attempts[$ip] = ['count' => 0, 'last_attempt' => 0];
    }

    // Wenn die letzte Sperre abgelaufen ist, zurücksetzen
    if ($attempts[$ip]['count'] >= MAX_LOGIN_ATTEMPTS &&
        time() - $attempts[$ip]['last_attempt'] >= LOCKOUT_DURATION) {
        $attempts[$ip] = ['count' => 0, 'last_attempt' => 0];
    }

    $attempts[$ip]['count']++;
    $attempts[$ip]['last_attempt'] = time();

    save_login_attempts($attempts);
}

/**
 * Login-Versuche für eine IP zurücksetzen
 */
function reset_login_attempts(string $ip) {
    $attempts = load_login_attempts();
    unset($attempts[$ip]);
    save_login_attempts($attempts);
}

/**
 * Login-Versuche aus Datei laden
 */
function load_login_attempts(): array {
    $file = DATA_DIR . 'login_attempts.json';
    if (!file_exists($file)) {
        return [];
    }
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

/**
 * Login-Versuche in Datei speichern
 */
function save_login_attempts(array $attempts) {
    $file = DATA_DIR . 'login_attempts.json';
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0770, true);
    }
    file_put_contents($file, json_encode($attempts, JSON_PRETTY_PRINT), LOCK_EX);
}

/**
 * Verbleibende Sperrzeit in Sekunden
 */
function get_lockout_remaining(string $ip): int {
    $attempts = load_login_attempts();
    if (!isset($attempts[$ip])) {
        return 0;
    }
    $record = $attempts[$ip];
    if ($record['count'] >= MAX_LOGIN_ATTEMPTS) {
        $remaining = LOCKOUT_DURATION - (time() - $record['last_attempt']);
        return max(0, $remaining);
    }
    return 0;
}
