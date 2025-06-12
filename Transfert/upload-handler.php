<?php
/**
 * Gestionnaire d'upload de fichiers
 * 
 * Ce script gère le téléversement de fichiers, incluant:
 * - La validation des fichiers
 * - La sécurisation des noms de fichiers
 * - La gestion des doublons
 * - L'analyse antivirus
 * 
 * @author  TeleLec
 * @version 1.3
 */

// TEMPORAIRE : Pour debug
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/upload_errors.log');

// Configuration des erreurs
ini_set('display_errors', 1);
error_reporting(E_ALL);

// AJOUT: Définir le fuseau horaire Europe/Paris pour tout le script
date_default_timezone_set('Europe/Paris');

header('Content-Type: application/json');

// Traitement de la requête
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tempFilePath = $_FILES["fileToUpload"]["tmp_name"];
    $originalName = $_FILES["fileToUpload"]["name"];
    $finalName = uniqid() . '_' . $originalName;
    $targetFile = __DIR__ . '/../uploads/' . $finalName;
    
    try {
        // Version simple sans includes
        $scanResult = moveAndScanFileSimple($tempFilePath, $targetFile);
        
        if ($scanResult['status'] === false) {
            echo json_encode([
                'status' => 'error',
                'message' => $scanResult['message'],
                'security_alert' => true
            ]);
            exit;
        }
        
        // Insertion en base
        $conn = new PDO("mysql:host=db;dbname=telelec;charset=utf8", 'telelecuser', 'userpassword');
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $uploadDate = date('Y-m-d H:i:s');
        $downloadCode = generateDownloadCode();
        $userCity = getCity($_SERVER['REMOTE_ADDR']);
        
        $antivirusStatus = $scanResult['status'] === true ? 'true' : 'warning';
        
        $sql = "INSERT INTO files (filename, upload_date, upload_ip, upload_city, download_code, antivirus_status, antivirus_message) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $finalName, 
            $uploadDate,
            $_SERVER['REMOTE_ADDR'], 
            $userCity,
            $downloadCode,
            $antivirusStatus,
            $scanResult['message']
        ]);

        $fileId = $conn->lastInsertId();
        
        // AJOUT: Logger l'analyse antivirus pour tous les fichiers
        $logSql = "INSERT INTO file_logs (file_id, action_type, action_date, user_ip, status, details) 
                   VALUES (?, 'antivirus_scan', NOW(), ?, ?, ?)";
        $logStmt = $conn->prepare($logSql);
        $logStmt->execute([
            $fileId,
            $_SERVER['REMOTE_ADDR'],
            $antivirusStatus,
            "Analyse antivirus: {$scanResult['message']}"
        ]);
        
        // Log success
        error_log("UPLOAD SUCCESS: ID={$fileId}, filename={$finalName}, scan_status={$antivirusStatus}");

        echo json_encode([
            'status' => 'success',
            'message' => 'Fichier uploadé avec succès',
            'filename' => $finalName,
            'original' => $originalName,
            'file_id' => $fileId,
            'scan_status' => $antivirusStatus
        ]);

    } catch (Exception $e) {
        if (file_exists($targetFile)) {
            unlink($targetFile);
        }
        
        error_log("UPLOAD ERROR: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Erreur lors du traitement: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Méthode HTTP non autorisée'
    ]);
}

// FONCTIONS INTÉGRÉES (pas de doublons)
function moveAndScanFileSimple($source, $destination) {
    if (!move_uploaded_file($source, $destination)) {
        return [
            'status' => false,
            'message' => 'Erreur lors du déplacement du fichier'
        ];
    }
    
    // SCAN BASIQUE D'ABORD (priorité EICAR) - OBLIGATOIRE
    $basicResult = scanFileBasicIntegrated($destination);
    
    // CORRECTION: Si virus détecté par scan basique, ARRÊTER IMMÉDIATEMENT
    if ($basicResult['status'] === false) {
        unlink($destination);
        error_log("VIRUS DÉTECTÉ par scan basique et fichier supprimé: " . $destination);
        return $basicResult; // RETOURNER ICI, pas de ClamAV
    }
    
    // Seulement si pas de virus détecté, essayer ClamAV
    try {
        $escapedPath = escapeshellarg($destination);
        $command = "timeout 5 clamscan --no-summary --stdout {$escapedPath} 2>&1";
        
        exec($command, $scanOutput, $scanCode);
        
        if ($scanCode === 1) {
            // ClamAV a détecté un virus
            unlink($destination);
            $virusInfo = implode(' ', $scanOutput);
            logVirusAttempt($destination, "ClamAV: " . $virusInfo);
            return [
                'status' => false,
                'message' => "🚨 VIRUS DÉTECTÉ par ClamAV: {$virusInfo}"
            ];
        } elseif ($scanCode === 0) {
            // ClamAV dit que c'est clean ET scan basique aussi
            return [
                'status' => true,
                'message' => '✅ Fichier vérifié et sain (ClamAV + Scan basique)'
            ];
        }
    } catch (Exception $e) {
        error_log("ClamAV indisponible: " . $e->getMessage());
    }
    
    // Si ClamAV échoue, retourner le résultat du scan basique (qui est clean)
    return $basicResult;
}

function scanFileBasicIntegrated($filepath) {
    $fileSize = filesize($filepath);
    if ($fileSize === false) {
        return ['status' => false, 'message' => 'Impossible de lire le fichier'];
    }
    
    $handle = fopen($filepath, 'rb');
    if (!$handle) {
        return ['status' => false, 'message' => 'Impossible d\'ouvrir le fichier'];
    }
    
    // Lire TOUT le fichier pour EICAR (c'est petit)
    $content = fread($handle, $fileSize);
    fclose($handle);
    
    // CORRECTION: Signatures EICAR COMPLÈTES et EXACTES
    $eicarSignatures = [
        'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*',
        'EICAR-STANDARD-ANTIVIRUS-TEST-FILE',
        'X5O!P%@AP[4\\PZX54(P^)7CC)7}$EICAR', // Version avec double backslash
        'EICAR-STANDARD-ANTIVIRUS-TEST'
    ];
    
    // DEBUG: Afficher le contenu pour vérifier
    error_log("SCAN DEBUG: Contenu fichier (100 premiers chars): " . substr($content, 0, 100));
    
    foreach ($eicarSignatures as $signature) {
        if (stripos($content, $signature) !== false) {
            error_log("SCAN DEBUG: EICAR DÉTECTÉ avec signature: " . $signature);
            logVirusAttempt($filepath, 'Fichier test EICAR détecté');
            return [
                'status' => false,  // S'assurer que c'est bien 'false'
                'message' => '🚨 VIRUS DÉTECTÉ: Fichier test EICAR'
            ];
        }
    }
    
    // Test encore plus simple : chercher juste "EICAR"
    if (stripos($content, 'EICAR') !== false) {
        error_log("SCAN DEBUG: EICAR trouvé dans le contenu !");
        logVirusAttempt($filepath, 'Fichier contenant EICAR détecté');
        return [
            'status' => false,  // S'assurer que c'est bien 'false'
            'message' => '🚨 VIRUS DÉTECTÉ: Fichier test EICAR (simple)'
        ];
    }
    
    // Patterns malveillants basiques
    $malwarePatterns = [
        '<?php' => 'Code PHP potentiellement dangereux',
        'eval(' => 'Code d\'évaluation suspect',
        'base64_decode(' => 'Décodage base64 suspect',
        'system(' => 'Commande système dangereuse',
        'exec(' => 'Exécution de commande dangereuse',
        'shell_exec(' => 'Exécution shell dangereuse'
    ];
    
    foreach ($malwarePatterns as $pattern => $description) {
        if (stripos($content, $pattern) !== false) {
            logVirusAttempt($filepath, $description);
            return [
                'status' => false,
                'message' => "🚨 MENACE DÉTECTÉE: {$description}"
            ];
        }
    }
    
    return [
        'status' => true,
        'message' => '✅ Fichier accepté (scan basique)'
    ];
}

function generateDownloadCode($length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

function getCity($ip) {
    if ($ip === '127.0.0.1' || $ip === '::1') {
        return 'Local';
    }
    
    $apiUrl = "http://ip-api.com/json/" . $ip;
    $response = @file_get_contents($apiUrl);
    if ($response) {
        $data = json_decode($response, true);
        return ($data && $data['status'] === 'success') ? $data['city'] : 'Inconnue';
    }
    return 'Inconnue';
}

// Améliorer la fonction de log :
function logVirusAttempt($filepath, $threat) {
    try {
        $conn = new PDO("mysql:host=db;dbname=telelec;charset=utf8", 'telelecuser', 'userpassword');
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // CORRECTION: Utiliser 'virus_detected' au lieu de 'virus_attempt'
        $sql = "INSERT INTO file_logs (action_type, action_date, user_ip, status, details) 
                VALUES ('virus_detected', NOW(), ?, 'blocked', ?)";
        $stmt = $conn->prepare($sql);
        $success = $stmt->execute([
            $_SERVER['REMOTE_ADDR'],
            "VIRUS DÉTECTÉ et bloqué: " . basename($filepath) . " - Menace: " . $threat
        ]);
        
        if ($success) {
            error_log("VIRUS LOGGED: " . $threat . " pour fichier " . basename($filepath));
        } else {
            error_log("ERREUR LOG VIRUS: échec insertion en base");
        }
        
    } catch (Exception $e) {
        error_log("ERREUR LOG VIRUS: " . $e->getMessage());
    }
}
?>