<?php
/**
 * Gestionnaire d'upload de fichiers
 * 
 * Ce script gère le téléversement de fichiers, incluant:
 * - La validation des fichiers
 * - La sécurisation des noms de fichiers
 * - La gestion des doublons
 * 
 * @author  TeleLec
 * @version 1.0
 */

// Configuration des erreurs
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

/**
 * Nettoie et sécurise le nom d'un fichier
 * 
 * @param string $filename Nom du fichier à nettoyer
 * @return string Nom du fichier nettoyé
 */
function sanitizeFilename($filename) {
    return preg_replace("/[^a-zA-Z0-9\._-]/", "_", basename($filename));
}

// Traitement de la requête
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /** @var string $targetDir Répertoire de destination des fichiers */
    $targetDir = __DIR__ . "/../uploads/";

    // Crée le répertoire d'uploads s'il n'existe pas
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    // Vérifie si un fichier a été envoyé
    if (!isset($_FILES["fileToUpload"])) {
        echo json_encode(['status' => 'error', 'message' => 'Aucun fichier reçu']);
        exit;
    }

    // Récupère et sécurise le nom du fichier
    $originalName = $_FILES["fileToUpload"]["name"];
    $safeName = sanitizeFilename($originalName);
    $fileInfo = pathinfo($safeName);
    $baseName = $fileInfo['filename'];
    $extension = isset($fileInfo['extension']) ? '.' . $fileInfo['extension'] : '';

    // Gestion des doublons : ajoute un compteur si le fichier existe déjà
    $finalName = $baseName . $extension;
    $targetFile = $targetDir . $finalName;
    $counter = 1;
    while (file_exists($targetFile)) {
        $finalName = $baseName . '_' . $counter . $extension;
        $targetFile = $targetDir . $finalName;
        $counter++;
    }

    // Déplace le fichier uploadé vers sa destination finale
    if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $targetFile)) {
        $userIp = $_SERVER['REMOTE_ADDR'];
        
        // CORRECTION: Utiliser le fuseau horaire européen comme dans l'affichage
        date_default_timezone_set('Europe/Paris');
        $uploadDate = date('Y-m-d H:i:s');
        
        // Fonction pour récupérer la ville depuis l'IP
        function getCity($ip) {
            $apiUrl = "http://ip-api.com/json/" . $ip;
            $response = @file_get_contents($apiUrl);
            if ($response) {
                $data = json_decode($response, true);
                return ($data && $data['status'] === 'success') ? $data['city'] : 'Inconnue';
            }
            return 'Inconnue';
        }
        
        $authorCity = getCity($userIp);
        
        // Générer un code de téléchargement unique
        function generateDownloadCode($length = 8) {
            $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $characters[rand(0, strlen($characters) - 1)];
            }
            return $code;
        }
        
        $downloadCode = generateDownloadCode();

        try {
            $pdo = new PDO('mysql:host=db;dbname=telelec;charset=utf8', 'telelecuser', 'userpassword');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Insérer le fichier dans la base
            $stmt = $pdo->prepare("INSERT INTO files (filename, upload_date, upload_ip, download_code) VALUES (?, ?, ?, ?)");
            $stmt->execute([$finalName, $uploadDate, $userIp, $downloadCode]);
            
            // Récupérer l'ID du fichier inséré
            $fileId = $pdo->lastInsertId();
            
            // Logger l'upload avec la ville
            $logSql = "INSERT INTO file_logs (file_id, action_type, user_ip, user_agent, city, status, details, action_date) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $logStmt = $pdo->prepare($logSql);
            $logStmt->execute([
                $fileId,
                'upload_start',
                $userIp,
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                $authorCity,
                'success',
                "Upload démarré - Fichier: {$finalName}",
                $uploadDate
            ]);

            echo json_encode([
                'status' => 'success',
                'filename' => $finalName,
                'original' => $originalName
            ]);
        } catch (PDOException $e) {
            error_log("Erreur lors de l'enregistrement en base de données : " . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Erreur lors de l\'upload']);
    }
    exit;
}
?>