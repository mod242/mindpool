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
            <a href="taxonomie.php" class="btn btn-secondary btn-sm">⚙ Stammdaten</a>
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
                    <button class="btn btn-secondary btn-sm" onclick="Dashboard.exportAllPDF()">Alle als PDF</button>
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
                    <label for="filter-abschlussart">Abschluss:</label>
                    <select id="filter-abschlussart">
                        <option value="">Alle</option>
                        <?php foreach ($abschlussarten as $ab): ?>
                            <?php if ($ab['status'] === 'aktiv'): ?>
                                <option value="<?= htmlspecialchars($ab['name']) ?>"><?= htmlspecialchars($ab['name']) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th class="foto-cell">Foto</th>
                        <th data-sort="nachname">Name <span class="sort-indicator">▲</span></th>
                        <th data-sort="wohnort">Wohnort <span class="sort-indicator">↕</span></th>
                        <th>Einsatzgebiet</th>
                        <th>Abschlüsse</th>
                        <th>Tätigkeit</th>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.2/jspdf.umd.min.js"></script>
    <script src="assets/app.js"></script>
</body>
</html>
