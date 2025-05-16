<?php
function generateDownloadCode($length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    $targetDir = "uploads/";
    $fileName = basename($_FILES["fileToUpload"]["name"]);
    $targetFilePath = $targetDir . $fileName;
    $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);
    $company = $_POST['company'];

    if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $targetFilePath)) {
        $host = 'db'; // Le nom du service dans le docker-compose
        $db = 'telelec';
        $user = 'telelecuser';
        $pass = 'userpassword';

        try {
            $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $downloadCode = generateDownloadCode();
            
            // Modification de la requête pour inclure toutes les colonnes obligatoires
            $sql = "INSERT INTO files (filename, company, download_code, downloaded) VALUES (?, ?, ?, 0)";
            $stmt = $conn->prepare($sql);
            if ($stmt->execute([$fileName, $company, $downloadCode])) {
                $response['success'] = true;
                $response['message'] = "Le fichier " . htmlspecialchars($fileName) . " a été téléchargé avec succès.<br>";
                $response['message'] .= "Code de téléchargement : " . htmlspecialchars($downloadCode);
            } else {
                throw new PDOException("Échec de l'insertion dans la base de données");
            }
        } catch (PDOException $e) {
            $response['message'] = "Erreur lors de l'ajout à la base de données : " . $e->getMessage();
        }
    } else {
        $response['message'] = "Désolé, une erreur s'est produite lors du téléchargement de votre fichier.";
    }
    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <!-- ...existing code... -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('uploadForm');
            const result = document.getElementById('uploadResult');

            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(form);
                
                try {
                    const response = await fetch('upload.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();
                    result.innerHTML = data.message;
                } catch (error) {
                    result.innerHTML = "Erreur lors de l'upload : " + error;
                }
            });
        });
    </script>
</head>
<body>
    <!-- ...existing code... -->
    <form id="uploadForm" method="post" enctype="multipart/form-data">
        <!-- ...existing form fields... -->
    </form>
    <div id="uploadResult"></div>
    <!-- ...existing code... -->
</body>
</html>