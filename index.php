<?php
session_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfert Tetelec</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/css/index.css">
    <script src="/script.js" defer></script>
</head>
<body>
    <?php include './Present/header.php'; ?>
    
    <main class="main-container">
        <h1 class="main-title">Bienvenue sur Telelec Transfert</h1>
        
        <div class="welcome-text">
            <div class="feature-card">
                <h3>🔒 Transfert Sécurisé</h3>
                <p>Envoyez et recevez vos fichiers en toute sécurité grâce à notre plateforme dédiée.</p>
            </div>

            <div class="feature-card">
                <h3>🚀 Simple et Rapide</h3>
                <p>Une interface intuitive pour faciliter vos transferts de fichiers.</p>
            </div>

        <div class="file-limit">
            <p>📁 Limite de transfert : 50 Go maximum par fichier</p>
        </div>

        <a href="/Transfert/send.php" class="cta-button">Commencer un transfert →</a>
    </main>

    <?php include './Present/footer.php'; ?>
</body>
</html>
