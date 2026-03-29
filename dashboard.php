<?php
/**
 * mindpool – Dashboard (Übersicht: Karte + Tabelle)
 */

require_once __DIR__ . '/auth.php';
require_auth();

$csrf = get_csrf_token();

// Taxonomien für Filter laden
$taxFile = DATA_DIR . 'taxonomien.json';
$taxonomien = file_exists($taxFile) ? json_decode(file_get_contents($taxFile), true) : [];
$einsatzgebiete = $taxonomien['einsatzgebiete']['eintraege'] ?? [];
$abschlussarten = $taxonomien['abschlussarten']['eintraege'] ?? [];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf) ?>">
    <title>mindpool – Trainer*innen-Pool</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
</head>
<body>
    <header class="app-header">
        <div class="header-left">
            <img src="assets/logo.png" alt="mindscool" class="logo-img">
            <span class="header-title">Trainer*innen-Pool</span>
        </div>
        <div class="header-right">
            <a href="taxonomie.php" class="btn btn-secondary btn-sm"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/></svg> Stammdaten</a>
            <a href="logout.php" class="btn btn-secondary btn-sm">Logout</a>
        </div>
    </header>

    <main class="app-main" id="dashboard-page">
        <!-- Karte -->
        <div class="map-container">
            <h2>Standorte</h2>
            <div id="map"></div>
        </div>

        <!-- Tabelle -->
        <div class="table-container">
            <div class="table-header">
                <h2>Trainer*innen</h2>
                <div class="table-actions">
                    <a href="form.php" class="btn btn-primary btn-sm">+ Neue*n Trainer*in anlegen</a>
                    <button class="btn btn-secondary btn-sm" onclick="Dashboard.exportTablePDF()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M10 13h4"/><path d="M10 17h4"/></svg> PDF</button>
                    <button class="btn btn-secondary btn-sm" onclick="Dashboard.exportCSV()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><path d="M12 3v18"/><path d="M18 9l-6-6-6 6"/></svg> CSV</button>
                </div>
            </div>

            <div class="table-filters">
                <div class="filter-group search-input">
                    <input type="search" id="search-input" placeholder="Suchen...">
                </div>
                <div class="filter-group">
                    <label for="filter-einsatzgebiet">Einsatzgebiet:</label>
                    <select id="filter-einsatzgebiet">
                        <option value="">Alle</option>
                        <?php foreach ($einsatzgebiete as $eg): ?>
                            <?php if ($eg['status'] === 'aktiv'): ?>
                                <option value="<?= htmlspecialchars($eg['name']) ?>"><?= htmlspecialchars($eg['name']) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter-land">Land:</label>
                    <select id="filter-land">
                        <option value="">Alle</option>
                        <option value="DE">DE</option>
                        <option value="AT">AT</option>
                        <option value="CH">CH</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter-angebot">Angebot:</label>
                    <select id="filter-angebot">
                        <option value="">Alle</option>
                        <option value="Lehrkräfte-Fortbildungen">Lehrkräfte-Fortbildungen</option>
                        <option value="Schülerworkshops">Schülerworkshops</option>
                        <option value="Schulentwicklung">Schulentwicklung</option>
                    </select>
                </div>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th class="foto-cell">Foto</th>
                        <th data-sort="nachname">Name <span class="sort-indicator">▲</span></th>
                        <th data-sort="plz">PLZ <span class="sort-indicator">↕</span></th>
                        <th data-sort="wohnort">Wohnort <span class="sort-indicator">↕</span></th>
                        <th>Einsatzgebiet</th>
                        <th>Wirkungsfelder</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody id="dozenten-tbody">
                    <tr><td colspan="7" class="no-data"><span class="spinner"></span> Laden...</td></tr>
                </tbody>
            </table>
        </div>
    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="assets/app.js"></script>
</body>
</html>
