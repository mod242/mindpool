<?php
/**
 * mindpool – JSON-API für CRUD-Operationen
 *
 * Alle Endpunkte über api.php?action=...
 * GET-Endpunkte: list, get, taxonomie_list, taxonomie_usage
 * POST-Endpunkte: create, update, deactivate, taxonomie_suggest, taxonomie_add,
 *                 taxonomie_rename, taxonomie_approve, taxonomie_merge, taxonomie_delete
 */

require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Authentifizierung prüfen
if (!is_authenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht authentifiziert']);
    exit;
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// POST-Endpunkte: CSRF prüfen
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    $csrf = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validate_csrf($csrf)) {
        http_response_code(403);
        echo json_encode(['error' => 'Ungültiges CSRF-Token']);
        exit;
    }
}

try {
    switch ($action) {
        // === Trainer*innen ===
        case 'list':
            handle_list();
            break;
        case 'get':
            handle_get();
            break;
        case 'create':
            require_post();
            handle_create($input);
            break;
        case 'update':
            require_post();
            handle_update($input);
            break;
        case 'deactivate':
            require_post();
            handle_deactivate($input);
            break;

        // === Taxonomien ===
        case 'taxonomie_list':
            handle_taxonomie_list();
            break;
        case 'taxonomie_usage':
            handle_taxonomie_usage();
            break;
        case 'taxonomie_suggest':
            require_post();
            handle_taxonomie_suggest($input);
            break;
        case 'taxonomie_add':
            require_post();
            handle_taxonomie_add($input);
            break;
        case 'taxonomie_rename':
            require_post();
            handle_taxonomie_rename($input);
            break;
        case 'taxonomie_approve':
            require_post();
            handle_taxonomie_approve($input);
            break;
        case 'taxonomie_merge':
            require_post();
            handle_taxonomie_merge($input);
            break;
        case 'taxonomie_delete':
            require_post();
            handle_taxonomie_delete($input);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unbekannte Aktion']);
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Interner Fehler: ' . $e->getMessage()]);
}

// === Hilfsfunktionen ===

function require_post() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'POST erforderlich']);
        exit;
    }
}

/**
 * JSON-Datei mit File-Locking lesen
 */
function read_json(string $file): array {
    $path = DATA_DIR . $file;
    if (!file_exists($path)) {
        return [];
    }
    $content = file_get_contents($path);
    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

/**
 * JSON-Datei mit File-Locking schreiben
 */
function write_json(string $file, $data): bool {
    $path = DATA_DIR . $file;
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($path, $json, LOCK_EX) !== false;
}

/**
 * UUID v4 generieren
 */
function generate_uuid(): string {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Text sanitizen
 */
function sanitize(string $value): string {
    return trim(htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8'));
}

/**
 * E-Mail validieren
 */
function valid_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Trainer*innen-Daten validieren
 */
function validate_dozent(array $data): array {
    $errors = [];

    if (empty(trim($data['vorname'] ?? ''))) {
        $errors[] = 'Vorname ist erforderlich';
    }
    if (empty(trim($data['nachname'] ?? ''))) {
        $errors[] = 'Nachname ist erforderlich';
    }
    $email = trim($data['email'] ?? '');
    if (!empty($email) && !valid_email($email)) {
        $errors[] = 'Ungültige E-Mail-Adresse';
    }
    if (empty(trim($data['wohnort'] ?? ''))) {
        $errors[] = 'Wohnort ist erforderlich';
    }
    $einsatzgebiete = $data['einsatzgebiete'] ?? [];
    if (empty($einsatzgebiete) || !is_array($einsatzgebiete) || count(array_filter($einsatzgebiete, 'trim')) === 0) {
        $errors[] = 'Mindestens ein Einsatzgebiet ist erforderlich';
    }

    // Foto-Größe prüfen
    if (!empty($data['foto_base64'])) {
        $fotoSize = strlen(base64_decode(
            preg_replace('/^data:image\/\w+;base64,/', '', $data['foto_base64'])
        ));
        if ($fotoSize > MAX_FOTO_SIZE) {
            $errors[] = 'Foto darf maximal 500 KB groß sein';
        }
    }

    // Projekte prüfen (max 5)
    if (!empty($data['projekte']) && is_array($data['projekte']) && count($data['projekte']) > 5) {
        $errors[] = 'Maximal 5 Projekte erlaubt';
    }

    return $errors;
}

/**
 * Trainer*innen-Daten aufbereiten
 */
function prepare_dozent(array $data, ?array $existing = null): array {
    $dozent = [
        'id'          => $existing['id'] ?? generate_uuid(),
        'vorname'     => sanitize($data['vorname'] ?? ''),
        'nachname'    => sanitize($data['nachname'] ?? ''),
        'email'       => sanitize($data['email'] ?? ''),
        'telefon'     => sanitize($data['telefon'] ?? ''),
        'webseite'    => sanitize($data['webseite'] ?? ''),
        'foto_base64' => $data['foto_base64'] ?? ($existing['foto_base64'] ?? ''),
        'plz'         => sanitize($data['plz'] ?? ''),
        'wohnort'     => sanitize($data['wohnort'] ?? ''),
        'land'        => sanitize($data['land'] ?? ''),
        'wohnort_lat' => floatval($data['wohnort_lat'] ?? 0),
        'wohnort_lng' => floatval($data['wohnort_lng'] ?? 0),
        'einsatzgebiete'      => [],
        'angebote'            => [],
        'aktuelle_taetigkeit' => sanitize($data['aktuelle_taetigkeit'] ?? ''),
        'wirkungsfelder'      => sanitize($data['wirkungsfelder'] ?? ''),
        'projekte'            => [],
        'akademische_abschluesse' => [],
        'aktiv'       => $existing['aktiv'] ?? true,
        'erstellt'    => $existing['erstellt'] ?? gmdate('Y-m-d\TH:i:s\Z'),
        'aktualisiert' => gmdate('Y-m-d\TH:i:s\Z'),
    ];

    // Einsatzgebiete sanitizen
    if (!empty($data['einsatzgebiete']) && is_array($data['einsatzgebiete'])) {
        foreach ($data['einsatzgebiete'] as $eg) {
            $eg = sanitize($eg);
            if ($eg !== '') {
                $dozent['einsatzgebiete'][] = $eg;
            }
        }
    }

    // Angebote sanitizen (nur erlaubte Werte)
    $erlaubte_angebote = ['Lehrkräfte-Fortbildungen', 'Schülerworkshops', 'Schulentwicklung'];
    if (!empty($data['angebote']) && is_array($data['angebote'])) {
        foreach ($data['angebote'] as $angebot) {
            if (in_array($angebot, $erlaubte_angebote, true)) {
                $dozent['angebote'][] = $angebot;
            }
        }
    }

    // Projekte sanitizen
    if (!empty($data['projekte']) && is_array($data['projekte'])) {
        foreach (array_slice($data['projekte'], 0, 5) as $projekt) {
            $p = sanitize($projekt);
            if ($p !== '') {
                $dozent['projekte'][] = $p;
            }
        }
    }

    // Akademische Abschlüsse sanitizen
    if (!empty($data['akademische_abschluesse']) && is_array($data['akademische_abschluesse'])) {
        foreach ($data['akademische_abschluesse'] as $abschluss) {
            if (!empty(trim($abschluss['art'] ?? '')) && !empty(trim($abschluss['fach'] ?? ''))) {
                $dozent['akademische_abschluesse'][] = [
                    'art'        => sanitize($abschluss['art']),
                    'fach'       => sanitize($abschluss['fach']),
                    'zusatzinfo' => sanitize($abschluss['zusatzinfo'] ?? ''),
                ];
            }
        }
    }

    return $dozent;
}

// === Handler-Funktionen ===

/**
 * Alle aktiven Trainer*innen auflisten
 */
function handle_list() {
    $dozenten = read_json('dozenten.json');
    $aktive = array_values(array_filter($dozenten, function($d) {
        return !empty($d['aktiv']);
    }));

    // Daten normalisieren + Foto-Daten kürzen
    foreach ($aktive as &$d) {
        if (!empty($d['foto_base64']) && strlen($d['foto_base64']) > 100) {
            $d['hat_foto'] = true;
        } else {
            $d['hat_foto'] = false;
        }
        // Migration: einsatzgebiet (String) → einsatzgebiete (Array)
        if (!isset($d['einsatzgebiete']) && !empty($d['einsatzgebiet'])) {
            $d['einsatzgebiete'] = [$d['einsatzgebiet']];
        }
        if (!isset($d['einsatzgebiete'])) {
            $d['einsatzgebiete'] = [];
        }
    }

    echo json_encode($aktive, JSON_UNESCAPED_UNICODE);
}

/**
 * Einzelne Person laden
 */
function handle_get() {
    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'ID erforderlich']);
        return;
    }

    $dozenten = read_json('dozenten.json');
    foreach ($dozenten as $d) {
        if ($d['id'] === $id) {
            // Migration: einsatzgebiet → einsatzgebiete
            if (!isset($d['einsatzgebiete']) && !empty($d['einsatzgebiet'])) {
                $d['einsatzgebiete'] = [$d['einsatzgebiet']];
            }
            if (!isset($d['einsatzgebiete'])) {
                $d['einsatzgebiete'] = [];
            }
            echo json_encode($d, JSON_UNESCAPED_UNICODE);
            return;
        }
    }

    http_response_code(404);
    echo json_encode(['error' => 'Person nicht gefunden']);
}

/**
 * Neue Person anlegen
 */
function handle_create(array $input) {
    $errors = validate_dozent($input);
    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode(['errors' => $errors]);
        return;
    }

    $dozent = prepare_dozent($input);

    // Datei mit Locking lesen und schreiben
    $file = DATA_DIR . 'dozenten.json';
    $fp = fopen($file, 'c+');
    if (!$fp) {
        throw new Exception('Datei konnte nicht geöffnet werden');
    }

    flock($fp, LOCK_EX);
    $content = stream_get_contents($fp);
    $dozenten = json_decode($content, true) ?: [];
    $dozenten[] = $dozent;

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($dozenten, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN);
    fclose($fp);

    echo json_encode(['success' => true, 'id' => $dozent['id']], JSON_UNESCAPED_UNICODE);
}

/**
 * Person aktualisieren
 */
function handle_update(array $input) {
    $id = $input['id'] ?? '';
    if (empty($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'ID erforderlich']);
        return;
    }

    $errors = validate_dozent($input);
    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode(['errors' => $errors]);
        return;
    }

    $file = DATA_DIR . 'dozenten.json';
    $fp = fopen($file, 'c+');
    if (!$fp) {
        throw new Exception('Datei konnte nicht geöffnet werden');
    }

    flock($fp, LOCK_EX);
    $content = stream_get_contents($fp);
    $dozenten = json_decode($content, true) ?: [];

    $found = false;
    foreach ($dozenten as &$d) {
        if ($d['id'] === $id) {
            $d = prepare_dozent($input, $d);
            $found = true;
            break;
        }
    }

    if (!$found) {
        flock($fp, LOCK_UN);
        fclose($fp);
        http_response_code(404);
        echo json_encode(['error' => 'Person nicht gefunden']);
        return;
    }

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($dozenten, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN);
    fclose($fp);

    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
}

/**
 * Person deaktivieren
 */
function handle_deactivate(array $input) {
    $id = $input['id'] ?? '';
    if (empty($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'ID erforderlich']);
        return;
    }

    $file = DATA_DIR . 'dozenten.json';
    $fp = fopen($file, 'c+');
    flock($fp, LOCK_EX);
    $content = stream_get_contents($fp);
    $dozenten = json_decode($content, true) ?: [];

    $found = false;
    foreach ($dozenten as &$d) {
        if ($d['id'] === $id) {
            $d['aktiv'] = false;
            $d['aktualisiert'] = gmdate('Y-m-d\TH:i:s\Z');
            $found = true;
            break;
        }
    }

    if (!$found) {
        flock($fp, LOCK_UN);
        fclose($fp);
        http_response_code(404);
        echo json_encode(['error' => 'Person nicht gefunden']);
        return;
    }

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($dozenten, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN);
    fclose($fp);

    echo json_encode(['success' => true]);
}

// === Taxonomie-Handler ===

/**
 * Gültige Taxonomie-Kategorien
 */
function valid_kategorie(string $k): bool {
    return in_array($k, ['abschlussarten', 'faecher', 'einsatzgebiete']);
}

/**
 * Taxonomie-Einträge einer Kategorie auflisten
 */
function handle_taxonomie_list() {
    $kategorie = $_GET['kategorie'] ?? '';
    if (!valid_kategorie($kategorie)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültige Kategorie']);
        return;
    }

    $tax = read_json('taxonomien.json');
    $eintraege = $tax[$kategorie]['eintraege'] ?? [];
    echo json_encode($eintraege, JSON_UNESCAPED_UNICODE);
}

/**
 * Verwendungszähler pro Taxonomie-Eintrag
 */
function handle_taxonomie_usage() {
    $kategorie = $_GET['kategorie'] ?? '';
    if (!valid_kategorie($kategorie)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültige Kategorie']);
        return;
    }

    $tax = read_json('taxonomien.json');
    $dozenten = read_json('dozenten.json');
    $eintraege = $tax[$kategorie]['eintraege'] ?? [];

    $usage = [];
    foreach ($eintraege as $eintrag) {
        $count = 0;
        foreach ($dozenten as $d) {
            if (!($d['aktiv'] ?? false)) continue;

            if ($kategorie === 'einsatzgebiete') {
                $gebiete = $d['einsatzgebiete'] ?? ($d['einsatzgebiet'] ?? '' ? [$d['einsatzgebiet']] : []);
                if (in_array($eintrag['name'], $gebiete, true)) {
                    $count++;
                }
            } else {
                // abschlussarten oder faecher → in akademische_abschluesse suchen
                $feld = $kategorie === 'abschlussarten' ? 'art' : 'fach';
                foreach ($d['akademische_abschluesse'] ?? [] as $abschluss) {
                    if (($abschluss[$feld] ?? '') === $eintrag['name']) {
                        $count++;
                        break; // Pro Person nur einmal zählen
                    }
                }
            }
        }
        $usage[] = [
            'id'    => $eintrag['id'],
            'name'  => $eintrag['name'],
            'status' => $eintrag['status'],
            'count' => $count,
        ];
    }

    echo json_encode($usage, JSON_UNESCAPED_UNICODE);
}

/**
 * Name normalisieren für neue Einträge
 */
function normalize_name(string $name): string {
    $name = trim($name);
    // Nur normalisieren wenn komplett lowercase
    if ($name === _mb_lower($name) && strlen($name) > 0) {
        $name = _mb_ucfirst($name);
    }
    return $name;
}

/**
 * Prüft ob ein Name in einer Kategorie bereits existiert (case-insensitive)
 */
function find_existing_entry(array $eintraege, string $name): ?array {
    $lower = _mb_lower($name);
    foreach ($eintraege as $e) {
        if (_mb_lower($e['name']) === $lower) {
            return $e;
        }
    }
    return null;
}

/**
 * UTF-8-sicheres strtolower (funktioniert auch ohne mbstring)
 */
function _mb_lower(string $s): string {
    return function_exists('mb_strtolower')
        ? mb_strtolower($s, 'UTF-8')
        : strtolower($s);
}

/**
 * Ersten Buchstaben groß (UTF-8-sicher)
 */
function _mb_ucfirst(string $s): string {
    if (function_exists('mb_strtoupper')) {
        return mb_strtoupper(mb_substr($s, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($s, 1, null, 'UTF-8');
    }
    return ucfirst($s);
}

/**
 * Nächste ID für eine Kategorie generieren
 */
function next_id(string $kategorie, array $eintraege): string {
    $prefix = [
        'abschlussarten' => 'ab',
        'faecher'        => 'fa',
        'einsatzgebiete' => 'eg',
    ][$kategorie];

    $max = 0;
    foreach ($eintraege as $e) {
        if (preg_match('/^' . $prefix . '-(\d+)$/', $e['id'], $m)) {
            $max = max($max, intval($m[1]));
        }
    }
    return sprintf('%s-%03d', $prefix, $max + 1);
}

/**
 * Neuen Taxonomie-Eintrag vorschlagen (aus dem Formular)
 */
function handle_taxonomie_suggest(array $input) {
    $kategorie = $input['kategorie'] ?? '';
    $name = $input['name'] ?? '';

    if (!valid_kategorie($kategorie) || empty(trim($name))) {
        http_response_code(400);
        echo json_encode(['error' => 'Kategorie und Name erforderlich']);
        return;
    }

    $name = normalize_name($name);

    $file = DATA_DIR . 'taxonomien.json';
    $fp = fopen($file, 'c+');
    flock($fp, LOCK_EX);
    $content = stream_get_contents($fp);
    $tax = json_decode($content, true) ?: [];

    $eintraege = $tax[$kategorie]['eintraege'] ?? [];

    // Duplikat-Prüfung
    $existing = find_existing_entry($eintraege, $name);
    if ($existing) {
        flock($fp, LOCK_UN);
        fclose($fp);
        echo json_encode([
            'success'  => true,
            'id'       => $existing['id'],
            'name'     => $existing['name'],
            'existing' => true,
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    // Neuen Eintrag als Vorschlag anlegen
    $id = next_id($kategorie, $eintraege);
    $neuer_eintrag = [
        'id'     => $id,
        'name'   => $name,
        'status' => 'vorschlag',
    ];

    $tax[$kategorie]['eintraege'][] = $neuer_eintrag;

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($tax, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN);
    fclose($fp);

    echo json_encode([
        'success'  => true,
        'id'       => $id,
        'name'     => $name,
        'existing' => false,
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Neuen Taxonomie-Eintrag als "aktiv" anlegen (aus der Verwaltung)
 */
function handle_taxonomie_add(array $input) {
    $kategorie = $input['kategorie'] ?? '';
    $name = $input['name'] ?? '';

    if (!valid_kategorie($kategorie) || empty(trim($name))) {
        http_response_code(400);
        echo json_encode(['error' => 'Kategorie und Name erforderlich']);
        return;
    }

    $name = normalize_name($name);

    $file = DATA_DIR . 'taxonomien.json';
    $fp = fopen($file, 'c+');
    flock($fp, LOCK_EX);
    $content = stream_get_contents($fp);
    $tax = json_decode($content, true) ?: [];

    $eintraege = $tax[$kategorie]['eintraege'] ?? [];

    // Duplikat-Prüfung
    if (find_existing_entry($eintraege, $name)) {
        flock($fp, LOCK_UN);
        fclose($fp);
        http_response_code(409);
        echo json_encode(['error' => 'Eintrag existiert bereits']);
        return;
    }

    $id = next_id($kategorie, $eintraege);
    $tax[$kategorie]['eintraege'][] = [
        'id'     => $id,
        'name'   => $name,
        'status' => 'aktiv',
    ];

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($tax, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN);
    fclose($fp);

    echo json_encode(['success' => true, 'id' => $id, 'name' => $name], JSON_UNESCAPED_UNICODE);
}

/**
 * Taxonomie-Eintrag umbenennen + Referenzen in dozenten.json aktualisieren
 */
function handle_taxonomie_rename(array $input) {
    $kategorie = $input['kategorie'] ?? '';
    $id = $input['id'] ?? '';
    $neuer_name = $input['neuer_name'] ?? '';

    if (!valid_kategorie($kategorie) || empty($id) || empty(trim($neuer_name))) {
        http_response_code(400);
        echo json_encode(['error' => 'Kategorie, ID und neuer Name erforderlich']);
        return;
    }

    $neuer_name = normalize_name($neuer_name);

    // Beide Dateien mit Locking öffnen (immer gleiche Reihenfolge)
    $tax_file = DATA_DIR . 'taxonomien.json';
    $doz_file = DATA_DIR . 'dozenten.json';

    $fp_tax = fopen($tax_file, 'c+');
    flock($fp_tax, LOCK_EX);
    $fp_doz = fopen($doz_file, 'c+');
    flock($fp_doz, LOCK_EX);

    $tax = json_decode(stream_get_contents($fp_tax), true) ?: [];
    $dozenten = json_decode(stream_get_contents($fp_doz), true) ?: [];

    // Eintrag finden und alten Namen merken
    $alter_name = null;
    $eintraege = &$tax[$kategorie]['eintraege'];
    foreach ($eintraege as &$e) {
        if ($e['id'] === $id) {
            $alter_name = $e['name'];

            // Duplikat-Prüfung (anderer Eintrag mit dem neuen Namen)
            $existing = find_existing_entry($eintraege, $neuer_name);
            if ($existing && $existing['id'] !== $id) {
                flock($fp_doz, LOCK_UN);
                fclose($fp_doz);
                flock($fp_tax, LOCK_UN);
                fclose($fp_tax);
                http_response_code(409);
                echo json_encode(['error' => 'Ein Eintrag mit diesem Namen existiert bereits']);
                return;
            }

            $e['name'] = $neuer_name;
            break;
        }
    }
    unset($e);

    if ($alter_name === null) {
        flock($fp_doz, LOCK_UN);
        fclose($fp_doz);
        flock($fp_tax, LOCK_UN);
        fclose($fp_tax);
        http_response_code(404);
        echo json_encode(['error' => 'Eintrag nicht gefunden']);
        return;
    }

    // Referenzen in dozenten.json aktualisieren
    update_dozenten_references($dozenten, $kategorie, $alter_name, $neuer_name);

    // Beide Dateien schreiben
    ftruncate($fp_tax, 0);
    rewind($fp_tax);
    fwrite($fp_tax, json_encode($tax, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    ftruncate($fp_doz, 0);
    rewind($fp_doz);
    fwrite($fp_doz, json_encode($dozenten, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    flock($fp_doz, LOCK_UN);
    fclose($fp_doz);
    flock($fp_tax, LOCK_UN);
    fclose($fp_tax);

    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
}

/**
 * Vorschlag freigeben (Status "vorschlag" → "aktiv")
 */
function handle_taxonomie_approve(array $input) {
    $kategorie = $input['kategorie'] ?? '';
    $id = $input['id'] ?? '';

    if (!valid_kategorie($kategorie) || empty($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Kategorie und ID erforderlich']);
        return;
    }

    $file = DATA_DIR . 'taxonomien.json';
    $fp = fopen($file, 'c+');
    flock($fp, LOCK_EX);
    $tax = json_decode(stream_get_contents($fp), true) ?: [];

    $found = false;
    foreach ($tax[$kategorie]['eintraege'] as &$e) {
        if ($e['id'] === $id) {
            $e['status'] = 'aktiv';
            $found = true;
            break;
        }
    }
    unset($e);

    if (!$found) {
        flock($fp, LOCK_UN);
        fclose($fp);
        http_response_code(404);
        echo json_encode(['error' => 'Eintrag nicht gefunden']);
        return;
    }

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($tax, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN);
    fclose($fp);

    echo json_encode(['success' => true]);
}

/**
 * Zwei Taxonomie-Einträge zusammenführen
 */
function handle_taxonomie_merge(array $input) {
    $kategorie = $input['kategorie'] ?? '';
    $quell_id = $input['quell_id'] ?? '';
    $ziel_id = $input['ziel_id'] ?? '';

    if (!valid_kategorie($kategorie) || empty($quell_id) || empty($ziel_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Kategorie, Quell-ID und Ziel-ID erforderlich']);
        return;
    }

    if ($quell_id === $ziel_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Quelle und Ziel dürfen nicht identisch sein']);
        return;
    }

    // Beide Dateien mit Locking öffnen
    $tax_file = DATA_DIR . 'taxonomien.json';
    $doz_file = DATA_DIR . 'dozenten.json';

    $fp_tax = fopen($tax_file, 'c+');
    flock($fp_tax, LOCK_EX);
    $fp_doz = fopen($doz_file, 'c+');
    flock($fp_doz, LOCK_EX);

    $tax = json_decode(stream_get_contents($fp_tax), true) ?: [];
    $dozenten = json_decode(stream_get_contents($fp_doz), true) ?: [];

    $eintraege = &$tax[$kategorie]['eintraege'];

    // Quell- und Ziel-Eintrag finden
    $quell_name = null;
    $ziel_name = null;
    $quell_index = null;

    foreach ($eintraege as $i => $e) {
        if ($e['id'] === $quell_id) {
            $quell_name = $e['name'];
            $quell_index = $i;
        }
        if ($e['id'] === $ziel_id) {
            $ziel_name = $e['name'];
        }
    }

    if ($quell_name === null || $ziel_name === null) {
        flock($fp_doz, LOCK_UN);
        fclose($fp_doz);
        flock($fp_tax, LOCK_UN);
        fclose($fp_tax);
        http_response_code(404);
        echo json_encode(['error' => 'Quell- oder Ziel-Eintrag nicht gefunden']);
        return;
    }

    // Referenzen in dozenten.json von Quelle auf Ziel umschreiben
    update_dozenten_references($dozenten, $kategorie, $quell_name, $ziel_name);

    // Quell-Eintrag aus Taxonomie löschen
    array_splice($eintraege, $quell_index, 1);

    // Beide Dateien schreiben
    ftruncate($fp_tax, 0);
    rewind($fp_tax);
    fwrite($fp_tax, json_encode($tax, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    ftruncate($fp_doz, 0);
    rewind($fp_doz);
    fwrite($fp_doz, json_encode($dozenten, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    flock($fp_doz, LOCK_UN);
    fclose($fp_doz);
    flock($fp_tax, LOCK_UN);
    fclose($fp_tax);

    echo json_encode(['success' => true]);
}

/**
 * Taxonomie-Eintrag löschen (nur wenn Verwendung = 0)
 */
function handle_taxonomie_delete(array $input) {
    $kategorie = $input['kategorie'] ?? '';
    $id = $input['id'] ?? '';

    if (!valid_kategorie($kategorie) || empty($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Kategorie und ID erforderlich']);
        return;
    }

    // Beide Dateien lesen um Verwendung zu prüfen
    $tax_file = DATA_DIR . 'taxonomien.json';
    $doz_file = DATA_DIR . 'dozenten.json';

    $fp_tax = fopen($tax_file, 'c+');
    flock($fp_tax, LOCK_EX);

    $tax = json_decode(stream_get_contents($fp_tax), true) ?: [];
    $dozenten = read_json('dozenten.json');

    $eintraege = &$tax[$kategorie]['eintraege'];

    // Eintrag finden
    $target = null;
    $target_index = null;
    foreach ($eintraege as $i => $e) {
        if ($e['id'] === $id) {
            $target = $e;
            $target_index = $i;
            break;
        }
    }

    if ($target === null) {
        flock($fp_tax, LOCK_UN);
        fclose($fp_tax);
        http_response_code(404);
        echo json_encode(['error' => 'Eintrag nicht gefunden']);
        return;
    }

    // Verwendung prüfen
    $count = count_usage($dozenten, $kategorie, $target['name']);
    if ($count > 0) {
        flock($fp_tax, LOCK_UN);
        fclose($fp_tax);
        http_response_code(409);
        echo json_encode([
            'error' => "Wird noch von $count Trainer*in(nen) verwendet. Erst zusammenführen.",
        ]);
        return;
    }

    // Löschen
    array_splice($eintraege, $target_index, 1);

    ftruncate($fp_tax, 0);
    rewind($fp_tax);
    fwrite($fp_tax, json_encode($tax, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($fp_tax, LOCK_UN);
    fclose($fp_tax);

    echo json_encode(['success' => true]);
}

/**
 * Referenzen in dozenten.json aktualisieren (für Rename und Merge)
 */
function update_dozenten_references(array &$dozenten, string $kategorie, string $alter_name, string $neuer_name) {
    foreach ($dozenten as &$d) {
        if ($kategorie === 'einsatzgebiete') {
            $gebiete = $d['einsatzgebiete'] ?? ($d['einsatzgebiet'] ?? '' ? [$d['einsatzgebiet']] : []);
            foreach ($gebiete as &$g) {
                if ($g === $alter_name) {
                    $g = $neuer_name;
                }
            }
            unset($g);
            $d['einsatzgebiete'] = array_values(array_unique($gebiete));
        } else {
            $feld = $kategorie === 'abschlussarten' ? 'art' : 'fach';
            if (!empty($d['akademische_abschluesse'])) {
                foreach ($d['akademische_abschluesse'] as &$abschluss) {
                    if (($abschluss[$feld] ?? '') === $alter_name) {
                        $abschluss[$feld] = $neuer_name;
                    }
                }
                unset($abschluss);
            }
        }
    }
    unset($d);
}

/**
 * Verwendung eines Taxonomie-Eintrags zählen
 */
function count_usage(array $dozenten, string $kategorie, string $name): int {
    $count = 0;
    foreach ($dozenten as $d) {
        if (!($d['aktiv'] ?? false)) continue;

        if ($kategorie === 'einsatzgebiete') {
            $gebiete = $d['einsatzgebiete'] ?? ($d['einsatzgebiet'] ?? '' ? [$d['einsatzgebiet']] : []);
            if (in_array($name, $gebiete, true)) {
                $count++;
            }
        } else {
            $feld = $kategorie === 'abschlussarten' ? 'art' : 'fach';
            foreach ($d['akademische_abschluesse'] ?? [] as $abschluss) {
                if (($abschluss[$feld] ?? '') === $name) {
                    $count++;
                    break;
                }
            }
        }
    }
    return $count;
}
