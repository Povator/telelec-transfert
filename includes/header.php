<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Europe/Paris');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfert Tetelec</title>
    <link rel="stylesheet" href="/style.css">
    
    <!-- Favicon avec chemin absolu -->
    <link rel="icon" href="/favicon/favicon.png" type="image/png">
    <link rel="shortcut icon" href="/favicon/favicon.png" type="image/png">
</head>
<body>
    <nav class="navbar">
        <div class="nav-left">
            <a href="/index.php" class="nav-item">Accueil</a>
            <a href="/Transfert/send.php" class="nav-item">Envoyer</a>
        </div>
        <div class="nav-right">
            <?php if (isset($_SESSION['admin']) && $_SESSION['admin'] === true): ?>
                <a href="/admin/dashboard.php" class="nav-item">Dashboard</a>
                <a href="/admin/logs.php" class="nav-item">Logs</a>
                <a href="/admin/security_threats.php" class="nav-item">Menaces</a>
                <a href="/admin/antivirus_details.php" class="nav-item">Antivirus</a>
                <a href="/admin/logout.php" class="nav-item">Déconnexion</a>
            <?php else: ?>
                <a href="/admin/login.php" class="nav-item">Admin</a>
            <?php endif; ?>
        </div>
    </nav>