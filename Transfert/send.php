<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Transfert TeLelec</title>
    <link rel="stylesheet" href="/style.css" />
    <link rel="stylesheet" href="/css/upload.css" />
</head>
<body>
    <?php include '../Present/header.php'; ?>

    <main>
        <h2>Glissez un fichier ici ou cliquez pour le sélectionner</h2>

        <form id="uploadForm">
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

            <div id="uploadResult"></div>

            <button type="submit">Envoyer le fichier</button>
        </form>
    </main>

    <?php include '../Present/footer.php'; ?>
    <script src="/js/send.js"></script>
</body>
</html>