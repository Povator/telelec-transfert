<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfert Tetelec</title>
    <link rel="stylesheet" href="/style.css">
    <script src="/script.js" defer></script>
</head>
<body>
    <?php include '../Present/header.php'; ?>
    
    <main>
        <h1>Upload de fichier</h1>

        <form action="" method="post" enctype="multipart/form-data">
            <label for="fileToUpload">Choisissez un fichier :</label>
            <input type="file" name="fileToUpload" id="fileToUpload" required>
            <button type="submit">Envoyer</button>
        </form>
    </main>

    <?php include '../Present/footer.php'; ?>
</body>
</html>