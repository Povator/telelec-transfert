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

        // 1. Vérifier si le fichier existe et récupérer son ID
        $findStmt = $pdo->prepare("SELECT id FROM files WHERE filename = ?");
        $findStmt->execute([$filename]);
        $fileRow = $findStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$fileRow) {
            throw new Exception("Fichier non trouvé dans la base de données");
        }
        
        $fileId = $fileRow['id'];
        
        // 2. Mettre à jour le code de téléchargement
        $updateStmt = $pdo->prepare("UPDATE files SET download_code = ? WHERE id = ?");
        $updateStmt->execute([$downloadCode, $fileId]);
        
        // 3. Désactiver les anciens codes A2F pour ce fichier
        $disableOldCodes = $pdo->prepare("UPDATE download_auth_codes SET used = TRUE WHERE file_id = ?");
        $disableOldCodes->execute([$fileId]);
        
        // 4. Insérer le nouveau code A2F
        $authSql = "INSERT INTO download_auth_codes (file_id, auth_code, expiration_date, used) VALUES (?, ?, ?, FALSE)";
        $authStmt = $pdo->prepare($authSql);
        $authStmt->execute([$fileId, $authCode, $expirationDate]);
        
        // 5. Logger les actions
        if (class_exists('FileLogger')) {
            $logger = new FileLogger($pdo);
            $logger->log($fileId, 'upload_complete', 'success', 
                "Upload finalisé - Fichier: {$filename}, Code: {$downloadCode}");
            
            $logger->log($fileId, 'auth_generate', 'success', 
                "Code A2F généré - Code: {$authCode}, Expiration: {$expirationDate}");
        }

        // 6. Valider la transaction
        $pdo->commit();

        // 7. Renvoyer les données au client
        echo json_encode([
            'success' => true,
            'code' => $downloadCode,
            'auth_code' => $authCode,
            'expiration_date' => $expirationDate,
            'url' => "/download.php?code=" . $downloadCode
        ]);
        
    } catch (PDOException $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        error_log("Erreur SQL dans finalize-upload.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    } catch (Exception $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        error_log("Erreur générale dans finalize-upload.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
}
?>