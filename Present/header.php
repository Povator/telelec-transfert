<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfert Tetelec</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>

<nav class="navbar">
    <div class="nav-left">
        <a href="/index.php" class="nav-item">Accueil</a>
        <a href="/Transfert/send.php" class="nav-item">Envoyer</a>
    </div>
    <div class="nav-right">
        <?php if (isset($_SESSION['admin']) && $_SESSION['admin'] === true): ?>
            <a href="/admin/dashboard.php" class="nav-button">Dashboard</a>
            <a href="/admin/logout.php" class="nav-button">Déconnexion</a>
        <?php else: ?>
            <a href="/admin/login.php" class="nav-button">Admin</a>
        <?php endif; ?>
    </div>
</nav>
</body>
</html>