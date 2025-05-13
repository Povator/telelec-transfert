<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfert Tetelec</title>
    <link rel="stylesheet" href="/style.css">
    <script src="/script.js"></script>
</head>
<body>
    <?php include '../Present/header.php'; ?>
    
    <main>
        <h1>Upload de fichier</h1>
        <?php
        $targetDir = __DIR__ . "/../uploads/"; // dossier où enregistrer le fichier

        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true); // créer le dossier s'il n'existe pas
        }

        if (isset($_FILES["fileToUpload"])) {
            $originalName = basename($_FILES["fileToUpload"]["name"]);
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
            $uniqueName = uniqid("file_", true) . "." . $extension;
            $targetFile = $targetDir . $uniqueName;

            if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $targetFile)) {
                echo "<h1>Le fichier a bien été uploadé : $originalName</h1><br>";
                echo "Fichier stocké sous : uploads/$uniqueName";

                // TODO : enregistrer dans la base de données (nom + date d'upload)
            } else {
                echo "Erreur lors de l'upload.";
            }
        } else {
            echo "Aucun fichier reçu.";
        }
        ?>
    </main>

    <?php include '../Present/footer.php'; ?>
</body>
</html>