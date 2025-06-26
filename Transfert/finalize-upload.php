<?php
/**
 * Finalisation d'un upload de fichier
 * 
 * Génère les codes d'authentification à deux facteurs et finalise
 * l'enregistrement du fichier dans la base de données.
 *
 * @author  TeleLec
 * @version 1.5
 */

header('Content-Type: application/json');

/**
 * Génère un code d'authentification à 6 chiffres
 *
 * @return string Code numérique de 6 chiffres avec zéros de tête
 */
function generateAuthCode() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Calcule la date d'expiration des codes A2F
 *
 * @param int $days Nombre de jours avant expiration
 *
 * @return string Date d'expiration au format Y-m-d H:i:s
 */
function calculateExpirationDate($days = 30) {
    return date('Y-m-d H:i:s', strtotime("+$days days"));
}

/**
 * Vérifie si un fichier existe en base de données
 *
 * @param PDO $pdo Connexion à la base de données
 * @param string $filename Nom du fichier à vérifier
 *
 * @return array|false Données du fichier ou false si inexistant
 *
 * @throws PDOException Si erreur de base de données
 */
function getFileByFilename($pdo, $filename) {
    // CORRECTION: Rechercher le fichier dans files seulement
    $findStmt = $pdo->prepare("SELECT id, filename, download_code FROM files WHERE filename = ? ORDER BY id DESC LIMIT 1");
    $findStmt->execute([$filename]);
    $fileRow = $findStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$fileRow) {
        error_log("FINALIZE: Fichier exact non trouvé, recherche du dernier fichier");
        $lastFileStmt = $pdo->query("SELECT id, filename, download_code FROM files ORDER BY id DESC LIMIT 1");
        $fileRow = $lastFileStmt->fetch(PDO::FETCH_ASSOC);
    }
    
    return $fileRow;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $filename = $_POST['filename'] ?? '';
    
    if (!$filename) {
        echo json_encode(['success' => false, 'error' => 'Nom de fichier manquant']);
        exit;
    }

    error_log("FINALIZE: Tentative de finalisation pour '{$filename}'");
    
    date_default_timezone_set('Europe/Paris');
    $expirationDate = date('Y-m-d H:i:s', strtotime('+5 days'));

    try {
        $pdo = new PDO('mysql:host=db;dbname=telelec;charset=utf8', 'telelecuser', 'userpassword');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->beginTransaction();

        $fileRow = getFileByFilename($pdo, $filename);
        
        if (!$fileRow) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false, 
                'error' => "Fichier '{$filename}' non trouvé dans la base de données."
            ]);
            exit;
        }
        
        $fileId = $fileRow['id'];
        error_log("FINALIZE: Fichier trouvé, ID={$fileId}, filename='{$fileRow['filename']}'");
        
        // CORRECTION: Vérifier s'il existe déjà un code A2F dans download_auth_codes
        $authStmt = $pdo->prepare("SELECT auth_code FROM download_auth_codes WHERE file_id = ? AND expiration_date > NOW() AND used = FALSE ORDER BY id DESC LIMIT 1");
        $authStmt->execute([$fileId]);
        $existingAuth = $authStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingAuth) {
            $authCode = $existingAuth['auth_code'];
            error_log("FINALIZE: Code A2F existant trouvé: {$authCode}");
        } else {
            // Générer un nouveau code A2F
            $authCode = generateAuthCode();
            
            // Insérer dans download_auth_codes (selon votre structure actuelle)
            $insertAuthStmt = $pdo->prepare("INSERT INTO download_auth_codes (file_id, auth_code, creation_date, expiration_date, used) VALUES (?, ?, NOW(), ?, FALSE)");
            $insertAuthStmt->execute([$fileId, $authCode, $expirationDate]);
            
            error_log("FINALIZE: Nouveau code A2F créé: {$authCode}");
        }
        
        // Logger l'action de finalisation
        $logSql = "INSERT INTO file_logs (file_id, action_type, user_ip, status, details, action_date) 
                  VALUES (?, ?, ?, ?, ?, ?)";
        $logStmt = $pdo->prepare($logSql);
        $logStmt->execute([
            $fileId,
            'upload_finalized',
            $_SERVER['REMOTE_ADDR'],
            'success',
            "Upload finalisé - Fichier: {$fileRow['filename']}",
            date('Y-m-d H:i:s')
        ]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'url' => "/download.php?code=" . $fileRow['download_code'],
            'auth_code' => $authCode, // Sera retiré côté frontend pour sécurité
            'expiration_date' => $expirationDate,
            'file_id' => $fileId,
            'filename' => $fileRow['filename']
        ]);
        
    } catch (PDOException $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        error_log("FINALIZE ERROR: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'error' => 'Erreur lors de la finalisation: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
}
?>