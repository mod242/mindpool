<?php
/**
 * mindpool – Taxonomie-Verwaltung (Stammdaten pflegen)
 */

require_once __DIR__ . '/auth.php';
require_auth();

$csrf = get_csrf_token();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf) ?>">
    <title>mindpool – Stammdaten verwalten</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <header class="app-header">
        <div class="header-left">
            <a href="dashboard.php"><img src="assets/logo.png" alt="mindscool" class="logo-img"></a>
            <span class="header-title">Stammdaten verwalten</span>
        </div>
        <div class="header-right">
            <a href="dashboard.php" class="btn btn-secondary btn-sm">Zurück zum Dashboard</a>
            <a href="logout.php" class="btn btn-secondary btn-sm">Logout</a>
        </div>
    </header>

    <main class="app-main" id="taxonomie-page">
        <!-- Hinweis-Banner für Vorschläge -->
        <div id="vorschlag-banner" class="alert alert-warning hidden mb-2"></div>

        <div class="table-container">
            <!-- Tabs -->
            <div class="tabs">
                <button class="tab active" data-kategorie="abschlussarten">Abschlussarten</button>
                <button class="tab" data-kategorie="faecher">Fächer</button>
                <button class="tab" data-kategorie="einsatzgebiete">Einsatzgebiete</button>
            </div>

            <!-- Suche -->
            <div class="table-filters">
                <div class="filter-group search-input">
                    <input type="search" id="tax-search" placeholder="Einträge durchsuchen...">
                </div>
            </div>

            <!-- Inhalt (wird per JS gefüllt) -->
            <div id="tax-content">
                <div class="no-data"><span class="spinner"></span> Laden...</div>
            </div>
        </div>

        <!-- Merge-Modal -->
        <div class="modal-overlay" id="merge-modal">
            <div class="modal">
                <div class="modal-header">
                    <h3>Einträge zusammenführen</h3>
                </div>
                <div class="modal-body">
                    <p>„<strong id="merge-quell-name"></strong>" zusammenführen mit:</p>
                    <input type="hidden" id="merge-quell-id">
                    <input type="hidden" id="merge-kategorie">
                    <div class="form-group mt-2">
                        <select id="merge-ziel">
                            <option value="">Bitte wählen...</option>
                        </select>
                    </div>
                    <p class="form-hint">
                        Alle Trainer*innen, die den Quell-Eintrag verwenden,
                        werden dem Ziel-Eintrag zugeordnet. Der Quell-Eintrag wird gelöscht.
                    </p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary btn-sm" onclick="TaxAdmin.closeMerge()">Abbrechen</button>
                    <button class="btn btn-primary btn-sm" onclick="TaxAdmin.doMerge()">Zusammenführen</button>
                </div>
            </div>
        </div>
    </main>

    <script src="assets/app.js"></script>
</body>
</html>
