<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

function generateDownloadCode($length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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

        $counter = 1;
        $targetFile = $targetDir . $baseName . '_' . $counter . $extension;

        while (file_exists($targetFile)) {
            $counter++;
            $targetFile = $targetDir . $baseName . '_' . $counter . $extension;
        }

        $safeName = $baseName . '_' . $counter . $extension;

        if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $targetFile)) {
            echo "<div class='upload-result'>";
            echo "<h3>Le fichier a bien été uploadé : " . htmlspecialchars($originalName) . "</h3>";
            echo "<p>Fichier stocké sous : uploads/" . htmlspecialchars($safeName) . "</p>";

            try {
                $pdo = new PDO('mysql:host=db;dbname=telelec;charset=utf8', 'telelecuser', 'userpassword');
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Génération du code de téléchargement
                $downloadCode = generateDownloadCode();

                // Insertion dans la base avec le code de téléchargement
                $stmt = $pdo->prepare("INSERT INTO files (filename, upload_date, company, download_code, downloaded) VALUES (?, NOW(), ?, ?, 0)");
                $stmt->execute([$safeName, 'Telelec', $downloadCode]);

                echo "<p>📎 Code de téléchargement : $downloadCode</p>";
                echo "<p>📎 Lien à partager au client :</p>";
                $downloadUrl = "/uploads/$safeName";
                echo "<a href=\"$downloadUrl\" target=\"_blank\">$downloadUrl</a>";

            } catch (PDOException $e) {
                echo "<p style='color:red;'>Erreur lors de l'ajout à la base de données : " . $e->getMessage() . "</p>";
            }
            echo "</div>";
        } else {
            echo "<p style='color:red;'>Erreur lors de l'upload.</p>";
        }
    }
    exit();
}
?>

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