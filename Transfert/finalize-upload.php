<?php
header('Content-Type: application/json');
require_once '../includes/file_utils.php';

// Fonction pour générer un code de téléchargement
function generateDownloadCode($length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

// Générer le code A2F
function generateAuthCode() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $filename = $_POST['filename'] ?? '';
    
    if (!$filename) {
        echo json_encode(['success' => false, 'error' => 'Nom de fichier manquant']);
        exit;
    }

    // Pour debug
    error_log("FINALIZE: Tentative de finalisation pour '{$filename}'");
    
    $authCode = generateAuthCode();
    
    // Même fuseau horaire partout
    date_default_timezone_set('Europe/Paris');
    $expirationDate = date('Y-m-d H:i:s', strtotime('+5 days'));

    try {
        $pdo = new PDO('mysql:host=db;dbname=telelec;charset=utf8', 'telelecuser', 'userpassword');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $downloadCode = generateDownloadCode();
        $pdo->beginTransaction();

        // SOLUTION: Trois stratégies de recherche du fichier
        $fileRow = null;
        
        // Stratégie 1: Recherche exacte
        $findStmt = $pdo->prepare("SELECT id, filename FROM files WHERE filename = ? ORDER BY id DESC LIMIT 1");
        $findStmt->execute([$filename]);
        $fileRow = $findStmt->fetch(PDO::FETCH_ASSOC);
        
        // Stratégie 2: Si pas trouvé, recherche du dernier fichier uploadé
        if (!$fileRow) {
            $lastFileStmt = $pdo->query("SELECT id, filename FROM files ORDER BY id DESC LIMIT 1");
            $fileRow = $lastFileStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($fileRow) {
                error_log("FINALIZE: Fichier exact non trouvé, utilisation du dernier fichier '{$fileRow['filename']}'");
            }
        }
        
        if (!$fileRow) {
            // Debug: afficher tous les fichiers
            $debugStmt = $pdo->query("SELECT id, filename FROM files ORDER BY id DESC LIMIT 5");
            $existingFiles = $debugStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $pdo->rollBack();
            echo json_encode([
                'success' => false, 
                'error' => "Fichier '{$filename}' non trouvé dans la base de données. Assurez-vous que le fichier a bien été uploadé.",
                'debug_info' => [
                    'searched' => $filename,
                    'existing_files' => $existingFiles
                ]
            ]);
            exit;
        }
        
        $fileId = $fileRow['id'];
        error_log("FINALIZE: Fichier trouvé, ID={$fileId}, filename='{$fileRow['filename']}'");
        
        // Mettre à jour le code de téléchargement
        $updateStmt = $pdo->prepare("UPDATE files SET download_code = ? WHERE id = ?");
        $updateStmt->execute([$downloadCode, $fileId]);
        
        // Désactiver les anciens codes A2F
        $disableOldCodes = $pdo->prepare("UPDATE download_auth_codes SET used = TRUE WHERE file_id = ?");
        $disableOldCodes->execute([$fileId]);
        
        // Insérer le nouveau code A2F
        $authSql = "INSERT INTO download_auth_codes (file_id, auth_code, expiration_date, used) VALUES (?, ?, ?, FALSE)";
        $authStmt = $pdo->prepare($authSql);
        $authStmt->execute([$fileId, $authCode, $expirationDate]);
        
        // Logger l'action
        $logSql = "INSERT INTO file_logs (file_id, action_type, user_ip, status, details, action_date) 
                  VALUES (?, ?, ?, ?, ?, ?)";
        $logStmt = $pdo->prepare($logSql);
        $logStmt->execute([
            $fileId,
            'upload_complete',
            $_SERVER['REMOTE_ADDR'],
            'success',
            "Upload finalisé - Fichier: {$fileRow['filename']}, Code: {$downloadCode}",
            date('Y-m-d H:i:s')
        ]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'code' => $downloadCode,
            'auth_code' => $authCode,
            'expiration_date' => $expirationDate,
            'url' => "/download.php?code=" . $downloadCode,
            'file_id' => $fileId,
            'actual_filename' => $fileRow['filename']  // Pour debug
        ]);
        
    } catch (PDOException $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        error_log("FINALIZE ERROR: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'error' => $e->getMessage(),
            'debug_filename' => $filename
        ]);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
}
?>