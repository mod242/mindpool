<?php
/**
 * mindpool – Login-Seite
 */

require_once __DIR__ . '/auth.php';

// Bereits eingeloggt? → Dashboard
if (is_authenticated()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$locked = false;

// Login-Versuch verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $ip = get_client_ip();

    if (is_locked_out($ip)) {
        $locked = true;
        $remaining = get_lockout_remaining($ip);
        $minutes = ceil($remaining / 60);
        $error = "Zu viele Versuche. Bitte " . $minutes . " Minute(n) warten.";
    } elseif (login($password)) {
        header('Location: dashboard.php');
        exit;
    } else {
        // Prüfen ob jetzt gesperrt
        if (is_locked_out($ip)) {
            $locked = true;
            $error = "Zu viele Versuche. Bitte warten.";
        } else {
            $error = "Zugang nicht möglich.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>mindpool – Login</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-logo">
                <img src="assets/logo.png" alt="mindscool" class="logo-img">
            </div>
            <h1 class="login-title">Trainer*innen-Pool</h1>
            <p class="login-subtitle">Bitte melde dich an, um fortzufahren.</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" action="index.php" class="login-form">
                <div class="form-group">
                    <label for="password">Passwort</label>
                    <input type="password"
                           id="password"
                           name="password"
                           placeholder="Passwort eingeben"
                           required
                           autofocus
                           autocomplete="current-password"
                           <?= $locked ? 'disabled' : '' ?>>
                </div>
                <button type="submit" class="btn btn-primary btn-block" <?= $locked ? 'disabled' : '' ?>>
                    Anmelden
                </button>
            </form>
        </div>
    </div>
</body>
</html>
