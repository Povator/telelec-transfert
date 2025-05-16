<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// 🔐 Vérification de la session
if (!isset($_SESSION['user_id'])) {
    die("Accès non autorisé");
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfert Tetelec</title>
    <link rel="stylesheet" href="/style.css">
    <style>
        #dropZone {
            border: 2px dashed #ED501C;
            padding: 50px;
            text-align: center;
            cursor: pointer;
            background-color: #f9f9f9;
            transition: background-color 0.2s ease;
            margin-top: 30px;
        }

        #dropZone.dragover {
            background-color: #ffeae3;
        }

        #uploadForm input[type="submit"] {
            margin-top: 20px;
        }

        #fileInfo {
            margin-top: 10px;
            padding: 10px;
            display: none;
            background-color: #e8f5e9;
            border-radius: 4px;
            text-align: center;
        }
    </style>
</head>
<body>
    <?php include '../Present/header.php'; ?>
    
    <main>
        <h1>Upload de fichier</h1>

        <form id="uploadForm" action="" method="post" enctype="multipart/form-data">
            <label for="company">Nom de l'entreprise :</label>
            <input type="text" name="company" id="company" required><br><br>

            <div id="dropZone" ondrop="handleDrop(event)" ondragover="handleDragOver(event)">
                <p>Déposez un fichier ici</p>
                <input type="file" name="fileToUpload" id="fileToUpload" hidden>
            </div>

            <div id="fileInfo"></div>
            <input type="submit" value="Envoyer le fichier" name="submit">
        </form>

        <?php
        $targetDir = __DIR__ . "/../uploads/";
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        if (isset($_FILES["fileToUpload"]) && isset($_POST["company"])) {
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
                if ($i > 1000) break;
            }

            if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $targetFile)) {
                echo "<h1>Le fichier a bien été uploadé : $safeName</h1><br>";
                echo "Fichier stocké sous : uploads/$safeName";

                // 🔄 Connexion à la base de données
                try {
                    $pdo = new PDO('mysql:host=Telelec-MySQL;dbname=telelec;charset=utf8', 'root', 'rootpassword');
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    $stmt = $pdo->prepare("INSERT INTO files (user_id, filename, filepath, size, uploaded_at, company_name) VALUES (?, ?, ?, ?, NOW(), ?)");
                    $stmt->execute([
                        $_SESSION['user_id'],
                        $safeName,
                        realpath($targetFile),
                        $_FILES["fileToUpload"]["size"],
                        $_POST["company"]
                    ]);

                    echo "<p>Fichier enregistré en base de données.</p>";

                } catch (PDOException $e) {
                    echo "<p>Erreur BDD : " . $e->getMessage() . "</p>";
                }

            } else {
                echo "Erreur lors de l'upload.";
            }
        }
        ?>
    </main>

    <?php include '../Present/footer.php'; ?>

    <script>
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileToUpload');
        const form = document.getElementById('uploadForm');
        const fileInfo = document.getElementById('fileInfo');

        function handleDragOver(e) {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.add('dragover');
        }

        function updateFileInfo(files) {
            if (files.length > 0) {
                const file = files[0];
                fileInfo.style.display = 'block';
                fileInfo.innerHTML = `Fichier sélectionné: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
                dropZone.style.borderColor = '#ED501C';
            } else {
                fileInfo.style.display = 'none';
                dropZone.style.borderColor = '#ED501C';
            }
        }

        function handleDrop(e) {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.remove('dragover');
            
            const dt = e.dataTransfer;
            const files = dt.files;

            fileInput.files = files;
            updateFileInfo(files);
        }

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });

        dropZone.addEventListener('click', () => {
            fileInput.click();
        });

        fileInput.addEventListener('change', () => {
            if(fileInput.files.length > 0) {
                updateFileInfo(fileInput.files);
            }
        });
    </script>
</body>
</html>
