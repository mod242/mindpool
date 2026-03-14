<?php
/**
 * mindpool – Einzelprofil-Ansicht und PDF-Export
 */

require_once __DIR__ . '/auth.php';
require_auth();

$csrf = get_csrf_token();

// Sammel-PDF-Modus
$pdfAll = ($_GET['pdf'] ?? '') === 'all';

// Einzelprofil laden
$dozent = null;
$id = $_GET['id'] ?? '';

if (!$pdfAll) {
    if (empty($id)) {
        header('Location: dashboard.php');
        exit;
    }

    $dozenten = json_decode(file_get_contents(DATA_DIR . 'dozenten.json'), true) ?: [];
    foreach ($dozenten as $d) {
        if ($d['id'] === $id) {
            $dozent = $d;
            break;
        }
    }

    if (!$dozent) {
        header('Location: dashboard.php');
        exit;
    }
}

$isPdfMode = isset($_GET['pdf']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf) ?>">
    <title>mindpool – <?= $pdfAll ? 'Sammel-PDF' : 'Profil: ' . htmlspecialchars($dozent['vorname'] . ' ' . $dozent['nachname']) ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <?php if (!$isPdfMode): ?>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
    <?php endif; ?>
</head>
<body>
    <?php if (!$isPdfMode): ?>
    <header class="app-header">
        <div class="header-left">
            <a href="dashboard.php"><img src="assets/logo.png" alt="mindscool" class="logo-img"></a>
            <span class="header-title">Profil</span>
        </div>
        <div class="header-right">
            <a href="dashboard.php" class="btn btn-secondary btn-sm">Zurück zur Übersicht</a>
        </div>
    </header>
    <?php endif; ?>

    <main class="app-main">
        <?php if ($pdfAll): ?>
            <!-- Sammel-PDF: Alle Trainer*innen laden und PDF generieren -->
            <div class="form-card">
                <div class="form-section" style="text-align: center; padding: 40px;">
                    <h2>Sammel-PDF wird erstellt...</h2>
                    <p class="text-muted mt-1">Bitte warten.</p>
                    <div class="spinner mt-2"></div>
                </div>
            </div>
        <?php else: ?>
            <!-- Einzelprofil -->
            <div class="form-card">
                <div class="profile-header">
                    <div class="profile-foto">
                        <?php if (!empty($dozent['foto_base64'])): ?>
                            <img src="<?= htmlspecialchars($dozent['foto_base64']) ?>" alt="Foto">
                        <?php else: ?>
                            <?= htmlspecialchars(mb_substr($dozent['vorname'], 0, 1) . mb_substr($dozent['nachname'], 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <h1 class="profile-name"><?= htmlspecialchars($dozent['vorname'] . ' ' . $dozent['nachname']) ?></h1>
                    <p class="profile-location">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg> <?= htmlspecialchars($dozent['wohnort']) ?> · <?= htmlspecialchars($dozent['einsatzgebiet']) ?>
                    </p>
                    <?php if (!empty($dozent['email']) || !empty($dozent['telefon'])): ?>
                        <p class="text-muted" style="margin-top: 8px;">
                            <?= htmlspecialchars($dozent['email']) ?>
                            <?php if (!empty($dozent['telefon'])): ?>
                                · <?= htmlspecialchars($dozent['telefon']) ?>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if (!empty($dozent['aktuelle_taetigkeit'])): ?>
                    <div class="profile-section">
                        <h3 class="profile-section-title">Aktuelle Tätigkeit</h3>
                        <p><?= htmlspecialchars($dozent['aktuelle_taetigkeit']) ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($dozent['akademische_abschluesse'])): ?>
                    <div class="profile-section">
                        <h3 class="profile-section-title">Akademische Abschlüsse</h3>
                        <?php foreach ($dozent['akademische_abschluesse'] as $a): ?>
                            <div class="profile-abschluss">
                                <span class="badge badge-purple"><?= htmlspecialchars($a['art']) ?></span>
                                <div class="profile-abschluss-details">
                                    <div class="profile-abschluss-fach"><?= htmlspecialchars($a['fach']) ?></div>
                                    <?php if (!empty($a['zusatzinfo'])): ?>
                                        <div class="profile-abschluss-zusatz"><?= htmlspecialchars($a['zusatzinfo']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($dozent['projekte'])): ?>
                    <div class="profile-section">
                        <h3 class="profile-section-title">Projekte</h3>
                        <ol class="profile-projekte">
                            <?php foreach ($dozent['projekte'] as $p): ?>
                                <li><?= htmlspecialchars($p) ?></li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                <?php endif; ?>

                <?php if (!$isPdfMode && !empty($dozent['wohnort_lat']) && $dozent['wohnort_lat'] != 0): ?>
                    <div class="profile-section">
                        <h3 class="profile-section-title">Standort</h3>
                        <div class="map-preview-container">
                            <div id="profile-map" style="height: 250px;"></div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!$isPdfMode): ?>
                    <div class="profile-actions">
                        <button class="btn btn-primary" onclick="generateProfilePDF()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M10 13h4"/><path d="M10 17h4"/></svg> PDF exportieren</button>
                        <a href="form.php?id=<?= htmlspecialchars($dozent['id']) ?>" class="btn btn-secondary"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><path d="M17 3a2.83 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg> Bearbeiten</a>
                        <a href="dashboard.php" class="btn btn-secondary">Zurück zur Übersicht</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <?php if (!$isPdfMode): ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <?php endif; ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="assets/app.js"></script>

    <?php if (!$isPdfMode && !empty($dozent['wohnort_lat']) && $dozent['wohnort_lat'] != 0): ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const mapEl = document.getElementById('profile-map');
            if (mapEl && typeof L !== 'undefined') {
                const map = L.map('profile-map').setView([<?= floatval($dozent['wohnort_lat']) ?>, <?= floatval($dozent['wohnort_lng']) ?>], 12);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap',
                    maxZoom: 18,
                }).addTo(map);
                L.marker([<?= floatval($dozent['wohnort_lat']) ?>, <?= floatval($dozent['wohnort_lng']) ?>], { icon: MapMarkerIcon }).addTo(map);
            }
        });
    </script>
    <?php endif; ?>

    <?php if (!$pdfAll && $isPdfMode): ?>
    <script>
        // PDF-Modus: Profil automatisch als PDF generieren
        document.addEventListener('DOMContentLoaded', async () => {
            const dozent = <?= json_encode($dozent, JSON_UNESCAPED_UNICODE) ?>;
            setTimeout(async () => {
                await PDFExport.generateSingle(dozent);
                window.close();
            }, 500);
        });
    </script>
    <?php endif; ?>

    <?php if ($pdfAll): ?>
    <script>
        // Sammel-PDF erstellen
        document.addEventListener('DOMContentLoaded', async () => {
            try {
                const dozenten = await apiGet('list');
                await PDFExport.generateAll(dozenten);
                Toast.show('Sammel-PDF erstellt!', 'success');
                setTimeout(() => window.close(), 2000);
            } catch (err) {
                Toast.show('Fehler: ' + err.message, 'error');
            }
        });
    </script>
    <?php endif; ?>

    <script>
        async function generateProfilePDF() {
            const dozent = <?= json_encode($dozent ?? new stdClass(), JSON_UNESCAPED_UNICODE) ?>;
            await PDFExport.generateSingle(dozent);
        }
    </script>
</body>
</html>
