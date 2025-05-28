<?php
require_once 'utils/virus_scan.php';
require_once 'utils/logger.php';

function generateDownloadCode($length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

function generateAuthCode() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    $targetDir = "uploads/";
    $fileName = basename($_FILES["fileToUpload"]["name"]);
    $targetFilePath = $targetDir . $fileName;
    $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);
    $company = $_POST['company'];

    // Déplacer d'abord vers un dossier temporaire pour le scan
    $tempDir = "uploads/temp/";
    if (!file_exists($tempDir)) {
        mkdir($tempDir, 0777, true);
    }
    $tempFilePath = $tempDir . uniqid() . '_' . $fileName;

    if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $tempFilePath)) {
        // Logger la tentative d'upload
        $logger->log(null, 'upload_start', 'info', "Début upload: {$fileName}");

        // Scanner le fichier
        $scanner = new VirusScan();
        $scanResult = $scanner->scanFile($tempFilePath);

        if ($scanResult['status'] === 'clean') {
            // Logger le résultat du scan
            $logger->log(null, 'scan', 'success', "Fichier sain: {$fileName}");

            if (rename($tempFilePath, $targetFilePath)) {
                $host = 'db'; // Le nom du service dans le docker-compose
                $db = 'telelec';
                $user = 'telelecuser';
                $pass = 'userpassword';

                try {
                    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
                    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    // Initialiser le logger
                    $logger = new FileLogger($conn);

                    $downloadCode = generateDownloadCode();
                    $authCode = generateAuthCode();
                    $expirationDate = date('Y-m-d H:i:s', strtotime('+5 days'));
                    $uploaderIp = $_SERVER['REMOTE_ADDR'];
                    
                    // Obtenir la ville de l'auteur
                    function getCity($ip) {
                        $apiUrl = "http://ip-api.com/json/" . $ip;
                        $response = @file_get_contents($apiUrl);
                        if ($response) {
                            $data = json_decode($response, true);
                            return ($data && $data['status'] === 'success') ? $data['city'] : 'Non renseigné';
                        }
                        return 'Non renseigné';
                    }
                    
                    $uploaderCity = getCity($uploaderIp);
                    
                    // Début de la transaction
                    $conn->beginTransaction();
                    
                    // Modification de la requête pour inclure l'IP et la ville
                    $sql = "INSERT INTO files (filename, company, upload_ip, upload_city, download_code, downloaded) 
                            VALUES (?, ?, ?, ?, ?, 0)";
                    $stmt = $conn->prepare($sql);
                    if ($stmt->execute([$fileName, $company, $uploaderIp, $uploaderCity, $downloadCode])) {
                        $fileId = $conn->lastInsertId();
                        
                        // Logger le succès de l'upload
                        $logger->log($fileId, 'upload_complete', 'success', 
                            "Upload réussi - Fichier: {$fileName}, Code: {$downloadCode}");

                        // Insertion du code A2F
                        $authSql = "INSERT INTO download_auth_codes (file_id, auth_code, expiration_date) 
                                   VALUES (?, ?, ?)";
                        $authStmt = $conn->prepare($authSql);
                        $authStmt->execute([$fileId, $authCode, $expirationDate]);

                        // Logger la génération du code A2F
                        $logger->log($fileId, 'auth_generate', 'success', 
                            "Code A2F généré - Expiration: {$expirationDate}");
                        
                        $conn->commit();
                        
                        $response['success'] = true;
                        $response['message'] = "Le fichier " . htmlspecialchars($fileName) . " a été téléchargé avec succès.<br>";
                        $response['message'] .= "Code de téléchargement : " . htmlspecialchars($downloadCode) . "<br>";
                        $response['message'] .= "Code A2F : " . htmlspecialchars($authCode) . "<br>";
                        $response['message'] .= "Date d'expiration : " . $expirationDate;
                    }
                } catch (PDOException $e) {
                    $logger->log(null, 'system_error', 'error', 
                        "Erreur base de données: {$e->getMessage()}");
                    $conn->rollBack();
                    $response['message'] = "Erreur lors de l'ajout à la base de données : " . $e->getMessage();
                }
            } else {
                $logger->log(null, 'upload_error', 'error', 
                    "Erreur déplacement fichier: {$fileName}");
                $response['message'] = "Erreur lors du déplacement du fichier.";
                unlink($tempFilePath); // Nettoyer le fichier temporaire
            }
        } else {
            // Logger fichier infecté
            $logger->log(null, 'scan', 'error', 
                "Fichier infecté: {$fileName} - {$scanResult['message']}");
            // Si le fichier est infecté ou erreur de scan
            $response['message'] = $scanResult['message'];
            unlink($tempFilePath); // Supprimer le fichier infecté
        }
    } else {
        $logger->log(null, 'upload_error', 'error', 
            "Échec upload: {$fileName}");
        $response['message'] = "Désolé, une erreur s'est produite lors du téléchargement de votre fichier.";
    }
    echo json_encode($response);
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $logger = new FileLogger($conn);
    $logger->log(null, 'page_access', 'info', 'Accès page upload');
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
        <div id="dropZone">
            <p>Glissez votre fichier ici ou cliquez pour sélectionner</p>
            <input type="file" name="fileToUpload" style="display: none;">
        </div>
        <div id="fileInfo" style="display: none;"></div>
        <div id="uploadProgress" style="display: none;">
            <div class="progress-info">
                <div class="progress-bar" style="width: 0%"></div>
            </div>
            <p class="progress-text">0%</p>
            <p class="speed-text"></p>
            <p class="time-text"></p>
        </div>
        <button type="submit" disabled>Envoyer le fichier</button>
    </form>
    <div id="uploadResult"></div>
    <!-- ...existing code... -->
</body>
</html>