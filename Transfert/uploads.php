<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
?>

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

        <form action="" method="post" enctype="multipart/form-data">
            <label for="fileToUpload">Choisissez un fichier :</label>
            <input type="file" name="fileToUpload" id="fileToUpload" required>
            <button type="submit">Envoyer</button>
        </form>

        <?php
       $targetDir = __DIR__ . "/../uploads/";
       if (!file_exists($targetDir)) {
           mkdir($targetDir, 0777, true);
       }
       
       if (isset($_FILES["fileToUpload"])) {
           $originalName = basename($_FILES["fileToUpload"]["name"]);
           $safeName = preg_replace("/[^a-zA-Z0-9\._-]/", "_", $originalName);
       
           $fileInfo = pathinfo($safeName);
           $baseName = $fileInfo['filename'];
           $extension = isset($fileInfo['extension']) ? '.' . $fileInfo['extension'] : '';
       
           $targetFile = $targetDir . $safeName;
           $i = 1;
       
           while (file_exists($targetFile)) {
               $safeName = $baseName . "_$i" . $extension;
               $targetFile = $targetDir . $safeName;
               $i++;
               if ($i > 1000) break; // sécurité anti-boucle infinie
           }
       
           if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $targetFile)) {
               echo "<h1>Le fichier a bien été uploadé : $safeName</h1><br>";
               echo "Fichier stocké sous : uploads/$safeName";
           } else {
               echo "Erreur lors de l'upload.";
           }
       }
        ?>
    </main>

    <?php include '../Present/footer.php'; ?>
</body>
</html>