<?php
/**
 * mindpool – Übersicht der Einsatzorte
 */

require_once __DIR__ . '/auth.php';
require_auth();

$csrf = get_csrf_token();

// Ort-Typen für Filter laden
$taxonomien = load_data_file('taxonomien.json');
$ort_typen = $taxonomien['ort_typen']['eintraege'] ?? [];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf) ?>">
    <title>mindpool – Orte</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <header class="app-header">
        <div class="header-left">
            <a href="dashboard.php"><img src="assets/logo.png" alt="mindscool" class="logo-img"></a>
            <span class="header-title">Einsatzorte</span>
        </div>
        <div class="header-right">
            <a href="dashboard.php" class="btn btn-secondary btn-sm">Zurück zum Dashboard</a>
            <a href="logout.php" class="btn btn-secondary btn-sm">Logout</a>
        </div>
    </header>

    <main class="app-main" id="orte-page">
        <div class="table-container">
            <div class="table-header">
                <h2>Orte</h2>
                <div class="table-actions">
                    <a href="ort-form.php" class="btn btn-primary btn-sm">+ Neuen Ort anlegen</a>
                </div>
            </div>

            <div class="table-filters">
                <div class="filter-group search-input">
                    <input type="search" id="orte-search" placeholder="Suchen...">
                </div>
                <div class="filter-group">
                    <label for="filter-ort-typ">Typ:</label>
                    <select id="filter-ort-typ">
                        <option value="">Alle</option>
                        <?php foreach ($ort_typen as $t): ?>
                            <?php if ($t['status'] === 'aktiv'): ?>
                                <option value="<?= htmlspecialchars($t['name']) ?>"><?= htmlspecialchars($t['name']) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th data-sort="name">Name <span class="sort-indicator">▲</span></th>
                        <th data-sort="typ">Typ <span class="sort-indicator">↕</span></th>
                        <th data-sort="ort">Ort <span class="sort-indicator">↕</span></th>
                        <th>Adresse</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody id="orte-tbody">
                    <tr><td colspan="5" class="no-data"><span class="spinner"></span> Laden...</td></tr>
                </tbody>
            </table>
        </div>
    </main>

    <script src="assets/app.js"></script>
</body>
</html>
