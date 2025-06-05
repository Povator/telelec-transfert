<?php
session_start(); // Déplacer session_start() tout en haut du fichier
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Envoyer un fichier - Telelec</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/css/upload.css">
    
    <!-- Favicon -->
    <link rel="icon" href="/flavicon/favicon.png" type="image/png">
    <link rel="shortcut icon" href="/flavicon/favicon.png" type="image/png">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <main>
        <h1>Envoyer un fichier</h1>

        <form id="uploadForm" method="post" enctype="multipart/form-data">
            <div id="dropZone" ondrop="handleDrop(event)" ondragover="handleDragOver(event)">
                <p>Déposez un fichier ici</p>
                <input type="file" name="fileToUpload" id="fileToUpload" hidden />
            </div>

            <div id="fileInfo"></div>

            <div id="uploadProgress">
                <div class="progress-info">
                    <div class="progress-bar" style="width: 0%"></div>
                </div>
                <p class="progress-text">0%</p>
                <p class="speed-text">Vitesse : -- MB/s</p>
                <p class="time-text">Temps restant : --:--</p>
            </div>

            <button type="button" id="cancelUploadBtn" style="display: none;" class="cancel-btn">
            ❌ Annuler l’upload
        </button>

            <div id="uploadResult"></div>

            <button type="submit">Envoyer le fichier</button>
        </form>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="/js/send.js"></script>
</body>
</html>