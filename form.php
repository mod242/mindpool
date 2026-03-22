<?php
/**
 * mindpool – Eingabeformular (Neu + Bearbeiten)
 */

require_once __DIR__ . '/auth.php';
require_auth();

$csrf = get_csrf_token();
$editId = $_GET['id'] ?? '';
$dozent = null;
$pageTitle = 'Neue*n Trainer*in anlegen';

// Bearbeitungsmodus: Daten laden
if ($editId) {
    $dozenten = json_decode(file_get_contents(DATA_DIR . 'dozenten.json'), true) ?: [];
    foreach ($dozenten as $d) {
        if ($d['id'] === $editId) {
            $dozent = $d;
            break;
        }
    }
    if (!$dozent) {
        header('Location: dashboard.php');
        exit;
    }
    $pageTitle = 'Profil bearbeiten: ' . htmlspecialchars($dozent['vorname'] . ' ' . $dozent['nachname']);
}

// Hilfsfunktion für Formularwerte
function val($dozent, $field, $default = '') {
    return htmlspecialchars($dozent[$field] ?? $default, ENT_QUOTES, 'UTF-8');
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
            <a href="dashboard.php"><img src="assets/logo.png" alt="mindscool" class="logo-img"></a>
            <span class="header-title"><?= htmlspecialchars($pageTitle) ?></span>
        </div>
        <div class="header-right">
            <a href="dashboard.php" class="btn btn-secondary btn-sm">Zurück</a>
        </div>
    </header>

    <main class="app-main">
        <form id="trainer-form" data-edit-id="<?= htmlspecialchars($editId) ?>">

            <!-- Abschnitt 1: Persönliche Daten -->
            <div class="form-card">
                <div class="form-section-header">
                    <div class="form-section-accent" style="background: var(--color-blue)"></div>
                    <h2 class="form-section-title">Persönliche Daten</h2>
                </div>
                <div class="form-section">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="vorname">Vorname <span class="required">*</span></label>
                            <input type="text" id="vorname" name="vorname" required
                                   value="<?= val($dozent, 'vorname') ?>"
                                   placeholder="Vorname">
                        </div>
                        <div class="form-group">
                            <label for="nachname">Nachname <span class="required">*</span></label>
                            <input type="text" id="nachname" name="nachname" required
                                   value="<?= val($dozent, 'nachname') ?>"
                                   placeholder="Nachname">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">E-Mail</label>
                            <input type="email" id="email" name="email"
                                   value="<?= val($dozent, 'email') ?>"
                                   placeholder="name@beispiel.de">
                        </div>
                        <div class="form-group">
                            <label for="telefon">Telefon</label>
                            <input type="tel" id="telefon" name="telefon"
                                   value="<?= val($dozent, 'telefon') ?>"
                                   placeholder="+49 170 1234567">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="webseite">Webseite</label>
                        <input type="url" id="webseite" name="webseite"
                               value="<?= val($dozent, 'webseite') ?>"
                               placeholder="https://www.beispiel.de">
                    </div>
                    <div class="form-group">
                        <label>Profilbild</label>
                        <div class="foto-upload">
                            <div class="foto-preview" id="foto-preview">
                                <?php if (!empty($dozent['foto_base64'])): ?>
                                    <img src="<?= htmlspecialchars($dozent['foto_base64']) ?>" alt="Vorschau">
                                <?php else: ?>
                                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.4"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                <?php endif; ?>
                            </div>
                            <div class="foto-upload-controls">
                                <input type="file" id="foto-input" accept="image/*">
                                <input type="hidden" id="foto-base64" name="foto_base64"
                                       value="<?= !empty($dozent['foto_base64']) ? htmlspecialchars($dozent['foto_base64']) : '' ?>">
                                <span class="form-hint">Max. 500 KB, JPG/PNG</span>
                                <?php if (!empty($dozent['foto_base64'])): ?>
                                    <button type="button" id="foto-remove" class="btn btn-sm btn-secondary">Foto entfernen</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Abschnitt 2: Ort & Einsatzgebiet -->
            <div class="form-card">
                <div class="form-section-header">
                    <div class="form-section-accent" style="background: var(--color-green)"></div>
                    <h2 class="form-section-title">Ort & Einsatzgebiet</h2>
                </div>
                <div class="form-section">
                    <div class="form-row">
                        <div class="form-group" style="flex:0 0 120px">
                            <label for="plz">PLZ</label>
                            <input type="text" id="plz" name="plz" maxlength="5" pattern="\d{5}"
                                   value="<?= val($dozent, 'plz') ?>"
                                   placeholder="z.B. 20095">
                        </div>
                        <div class="form-group">
                            <label for="wohnort">Wohnort <span class="required">*</span></label>
                            <input type="text" id="wohnort" name="wohnort" required
                                   value="<?= val($dozent, 'wohnort') ?>"
                                   placeholder="z.B. Hamburg">
                        </div>
                    </div>
                    <div class="form-group" style="display:none">
                        <input type="hidden" id="wohnort_lat" name="wohnort_lat"
                               value="<?= val($dozent, 'wohnort_lat', '0') ?>">
                        <input type="hidden" id="wohnort_lng" name="wohnort_lng"
                               value="<?= val($dozent, 'wohnort_lng', '0') ?>">
                    </div>
                    <div class="form-group">
                        <label>Einsatzgebiet <span class="required">*</span></label>
                        <div data-combobox="einsatzgebiete"
                             data-name="einsatzgebiet"
                             data-placeholder="Einsatzgebiet wählen oder suchen..."
                             data-required="true"
                             data-value="<?= val($dozent, 'einsatzgebiet') ?>"></div>
                    </div>
                    <?php if (!empty($dozent['wohnort_lat']) && $dozent['wohnort_lat'] != 0): ?>
                        <div class="form-group">
                            <label>Standort-Vorschau</label>
                            <div class="map-preview-container">
                                <div id="map-preview"></div>
                            </div>
                            <span class="form-hint">Klicke auf die Karte, um den Standort zu korrigieren.</span>
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label>Standort-Vorschau</label>
                            <div class="map-preview-container">
                                <div id="map-preview"></div>
                            </div>
                            <span class="form-hint">Wird automatisch nach Eingabe des Wohnorts angezeigt. Klicke auf die Karte, um den Standort zu korrigieren.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Abschnitt 3: Berufliches -->
            <div class="form-card">
                <div class="form-section-header">
                    <div class="form-section-accent" style="background: var(--color-purple)"></div>
                    <h2 class="form-section-title">Berufliches</h2>
                </div>
                <div class="form-section">
                    <div class="form-group">
                        <label for="aktuelle_taetigkeit">Aktuelle berufliche Tätigkeit</label>
                        <textarea id="aktuelle_taetigkeit" name="aktuelle_taetigkeit"
                                  maxlength="300" data-maxlength="300"
                                  placeholder="z.B. Freiberufliche Trainerin für Achtsamkeit und Stressbewältigung"
                        ><?= val($dozent, 'aktuelle_taetigkeit') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Abschnitt 4: Projekte -->
            <div class="form-card">
                <div class="form-section-header">
                    <div class="form-section-accent" style="background: var(--color-orange)"></div>
                    <h2 class="form-section-title">Vergangene & aktuelle Projekte</h2>
                </div>
                <div class="form-section">
                    <div id="projekte-list">
                        <?php
                        $projekte = $dozent['projekte'] ?? [];
                        foreach ($projekte as $p):
                        ?>
                            <div class="dynamic-list-item">
                                <div class="item-fields">
                                    <input type="text" name="projekte[]" maxlength="200"
                                           value="<?= htmlspecialchars($p, ENT_QUOTES, 'UTF-8') ?>"
                                           placeholder="z.B. mindscool Grundschulprogramm Hamburg (2023–heute)">
                                </div>
                                <button type="button" class="btn-icon btn-remove" title="Entfernen"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" id="add-projekt" class="btn btn-secondary btn-sm add-item-btn"
                            <?= count($projekte) >= 5 ? 'disabled' : '' ?>>
                        + Projekt hinzufügen
                    </button>
                    <span class="form-hint">Maximal 5 Projekte</span>
                </div>
            </div>

            <!-- Abschnitt 5: Akademische Abschlüsse -->
            <div class="form-card">
                <div class="form-section-header">
                    <div class="form-section-accent" style="background: var(--color-blue)"></div>
                    <h2 class="form-section-title">Akademische Abschlüsse</h2>
                </div>
                <div class="form-section">
                    <div id="abschluesse-list">
                        <!-- Bestehende Abschlüsse werden per JS geladen -->
                    </div>
                    <button type="button" id="add-abschluss" class="btn btn-secondary btn-sm add-item-btn">
                        + Abschluss hinzufügen
                    </button>
                </div>
            </div>

            <!-- Footer -->
            <div class="form-footer">
                <button type="submit" class="btn btn-primary">Speichern</button>
                <a href="dashboard.php" class="btn btn-secondary">Abbrechen</a>
                <?php if ($editId): ?>
                    <button type="button" class="btn btn-danger" style="margin-left: auto;"
                            onclick="deactivateTrainer('<?= htmlspecialchars($editId) ?>')">
                        Trainer*in deaktivieren
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <script src="assets/app.js"></script>
    <script>
        // Bestehende Abschlüsse im Bearbeitungsmodus laden
        <?php if ($dozent && !empty($dozent['akademische_abschluesse'])): ?>
        document.addEventListener('DOMContentLoaded', () => {
            const container = document.getElementById('abschluesse-list');
            const abschluesse = <?= json_encode($dozent['akademische_abschluesse'], JSON_UNESCAPED_UNICODE) ?>;
            abschluesse.forEach(a => {
                TrainerForm.addAbschlussRow(container, a);
            });
        });
        <?php endif; ?>

        // Vorschaukarte initialisieren wenn Koordinaten vorhanden
        <?php if (!empty($dozent['wohnort_lat']) && $dozent['wohnort_lat'] != 0): ?>
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                TrainerForm.updatePreviewMap(<?= floatval($dozent['wohnort_lat']) ?>, <?= floatval($dozent['wohnort_lng']) ?>);
            }, 300);
        });
        <?php endif; ?>

        // Deaktivieren-Funktion
        async function deactivateTrainer(id) {
            if (!confirm('Trainer*in wirklich deaktivieren? Die Person wird aus der aktiven Liste entfernt.')) return;
            try {
                await apiPost('deactivate', { id });
                Toast.show('Person wurde deaktiviert.', 'success');
                setTimeout(() => window.location.href = 'dashboard.php', 1000);
            } catch (err) {
                Toast.show('Fehler: ' + err.message, 'error');
            }
        }
    </script>
</body>
</html>
