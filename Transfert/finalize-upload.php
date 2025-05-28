<?php
header('Content-Type: application/json');
require_once '../utils/logger.php';

// Fonction pour générer un code de téléchargement
function generateDownloadCode($length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

// Générer le code A2F et sa date d'expiration
function generateAuthCode() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $filename = $_POST['filename'] ?? '';

    if (!$filename) {
        echo json_encode(['success' => false, 'error' => 'Nom de fichier manquant']);
        exit;
    }

    $authCode = generateAuthCode();
    $expirationDate = date('Y-m-d H:i:s', strtotime('+5 days'));

    try {
        $pdo = new PDO('mysql:host=db;dbname=telelec;charset=utf8', 'telelecuser', 'userpassword');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $downloadCode = generateDownloadCode();

        // Début de la transaction
        $pdo->beginTransaction();

        // Insertion du fichier
        $stmt = $pdo->prepare("INSERT INTO files (filename, download_code, company) VALUES (?, ?, 'TeLelec')");
        $logger = new FileLogger($pdo);
    
        if ($stmt->execute([$filename, $downloadCode])) {
            $fileId = $pdo->lastInsertId();
            
            // Log de la finalisation de l'upload
            $logger->log($fileId, 'upload_complete', 'success', 
                "Upload finalisé - Fichier: {$filename}, Code: {$downloadCode}");
            
            // Log de la génération du code A2F
            $logger->log($fileId, 'auth_generate', 'success', 
                "Code A2F généré - Code: {$authCode}, Expiration: {$expirationDate}");
            
            // Insertion du code A2F
            $authSql = "INSERT INTO download_auth_codes (file_id, auth_code, expiration_date) VALUES (?, ?, ?)";
            $authStmt = $pdo->prepare($authSql);
            $authStmt->execute([$fileId, $authCode, $expirationDate]);

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'code' => $downloadCode,
                'auth_code' => $authCode,
                'expiration_date' => $expirationDate,
                'url' => "/download.php?code=" . $downloadCode
            ]);
        }
    } catch (PDOException $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (Exception $e) {
        $logger->log(null, 'error', 'error', 'Erreur finalisation: ' . $e->getMessage());
    }
}
?>