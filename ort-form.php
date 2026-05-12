<?php
/**
 * mindpool – Eingabeformular für Orte (Neu + Bearbeiten)
 */

require_once __DIR__ . '/auth.php';
require_auth();

$csrf = get_csrf_token();
$editId = $_GET['id'] ?? '';
$ort = null;
$pageTitle = 'Neuen Ort anlegen';

if ($editId) {
    $orte = load_data_file('orte.json');
    foreach ($orte as $o) {
        if ($o['id'] === $editId) {
            $ort = $o;
            break;
        }
    }
    if (!$ort) {
        header('Location: orte.php');
        exit;
    }
    $pageTitle = 'Ort bearbeiten: ' . htmlspecialchars($ort['name']);
}

// Aktive Ort-Typen für die Auswahl laden
$taxonomien = load_data_file('taxonomien.json');
$ort_typen = $taxonomien['ort_typen']['eintraege'] ?? [];

function ortval($ort, $field, $default = '') {
    return htmlspecialchars($ort[$field] ?? $default, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf) ?>">
    <title>mindpool – <?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
</head>
<body>
    <header class="app-header">
        <div class="header-left">
            <a href="orte.php"><img src="assets/logo.png" alt="mindscool" class="logo-img"></a>
            <span class="header-title"><?= htmlspecialchars($pageTitle) ?></span>
        </div>
        <div class="header-right">
            <a href="orte.php" class="btn btn-secondary btn-sm">Zurück</a>
        </div>
    </header>

    <main class="app-main">
        <form id="ort-form" data-edit-id="<?= htmlspecialchars($editId) ?>">

            <!-- Abschnitt 1: Basisdaten -->
            <div class="form-card">
                <div class="form-section-header">
                    <div class="form-section-accent" style="background: var(--color-blue)"></div>
                    <h2 class="form-section-title">Basisdaten</h2>
                </div>
                <div class="form-section">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Name der Institution <span class="required">*</span></label>
                            <input type="text" id="name" name="name" required
                                   value="<?= ortval($ort, 'name') ?>"
                                   placeholder="z.B. Gymnasium Beispielstadt">
                        </div>
                        <div class="form-group" style="flex:0 0 240px">
                            <label for="typ">Typ <span class="required">*</span></label>
                            <select id="typ" name="typ" required>
                                <option value="">– bitte wählen –</option>
                                <?php foreach ($ort_typen as $t): ?>
                                    <?php if ($t['status'] === 'aktiv'): ?>
                                        <option value="<?= htmlspecialchars($t['name']) ?>"
                                            <?= ($ort['typ'] ?? '') === $t['name'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($t['name']) ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <span class="form-hint">Stammdaten unter Stammdaten → Ort-Typen pflegen.</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="webseite">Webseite</label>
                        <input type="url" id="webseite" name="webseite"
                               value="<?= ortval($ort, 'webseite') ?>"
                               placeholder="https://www.beispiel.de">
                    </div>
                </div>
            </div>

            <!-- Abschnitt 2: Adresse -->
            <div class="form-card">
                <div class="form-section-header">
                    <div class="form-section-accent" style="background: var(--color-green)"></div>
                    <h2 class="form-section-title">Adresse</h2>
                </div>
                <div class="form-section">
                    <div class="form-group">
                        <label for="strasse">Straße + Hausnummer</label>
                        <input type="text" id="strasse" name="strasse"
                               value="<?= ortval($ort, 'strasse') ?>"
                               placeholder="z.B. Musterweg 1">
                    </div>
                    <div class="form-row">
                        <div class="form-group" style="flex:0 0 120px">
                            <label for="plz">PLZ</label>
                            <input type="text" id="plz" name="plz" maxlength="10"
                                   value="<?= ortval($ort, 'plz') ?>"
                                   placeholder="z.B. 20095">
                        </div>
                        <div class="form-group">
                            <label for="ort-stadt">Ort <span class="required">*</span></label>
                            <input type="text" id="ort-stadt" name="ort" required
                                   value="<?= ortval($ort, 'ort') ?>"
                                   placeholder="z.B. Hamburg">
                        </div>
                        <div class="form-group" style="flex:0 0 100px">
                            <label for="land">Land</label>
                            <select id="land" name="land">
                                <option value="">–</option>
                                <option value="DE" <?= ($ort['land'] ?? '') === 'DE' ? 'selected' : '' ?>>DE</option>
                                <option value="AT" <?= ($ort['land'] ?? '') === 'AT' ? 'selected' : '' ?>>AT</option>
                                <option value="CH" <?= ($ort['land'] ?? '') === 'CH' ? 'selected' : '' ?>>CH</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" style="display:none">
                        <input type="hidden" id="lat" name="lat"
                               value="<?= ortval($ort, 'lat', '0') ?>">
                        <input type="hidden" id="lng" name="lng"
                               value="<?= ortval($ort, 'lng', '0') ?>">
                    </div>
                    <div class="form-group">
                        <label>Standort-Vorschau</label>
                        <div class="map-preview-container">
                            <div id="ort-map-preview"></div>
                        </div>
                        <span class="form-hint">Wird automatisch nach Eingabe der Adresse gesetzt. Klicke auf die Karte, um den Standort zu korrigieren.</span>
                    </div>
                </div>
            </div>

            <!-- Abschnitt 3: Notizen -->
            <div class="form-card">
                <div class="form-section-header">
                    <div class="form-section-accent" style="background: var(--color-purple)"></div>
                    <h2 class="form-section-title">Notizen</h2>
                </div>
                <div class="form-section">
                    <div class="form-group">
                        <label for="notizen">Anmerkungen (intern)</label>
                        <textarea id="notizen" name="notizen" rows="3"
                                  placeholder="Optional"><?= ortval($ort, 'notizen') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <a href="orte.php" class="btn btn-secondary">Abbrechen</a>
                <?php if ($editId): ?>
                    <button type="button" class="btn btn-danger" onclick="OrtForm.deactivate()">Deaktivieren</button>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary">Speichern</button>
            </div>
        </form>
    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <script src="assets/app.js"></script>
</body>
</html>
