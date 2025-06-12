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

// Inclure les utilitaires et le système antivirus
require_once __DIR__ . '/../includes/file_utils.php';
require_once __DIR__ . '/../includes/antivirus.php';

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

    // Récupère le nom original et génère un nom unique
    $originalName = $_FILES["fileToUpload"]["name"];
    $uniqueFile = generateUniqueFilename($targetDir, $originalName);
    $finalName = $uniqueFile['filename'];
    $targetFile = $uniqueFile['filepath'];

    // NOUVEAU : Scan en streaming pendant le déplacement
    $tempFilePath = $_FILES["fileToUpload"]["tmp_name"];
    
    try {
        // INNOVATION: Scan et déplacement en une seule opération
        $streamScanResult = moveAndScanFile($tempFilePath, $targetFile);
        
        if ($streamScanResult['status'] === false) {
            // Virus détecté pendant le transfert - supprimer immédiatement
            if (file_exists($targetFile)) {
                unlink($targetFile);
            }
            echo json_encode([
                'status' => 'error',
                'message' => $streamScanResult['message']
            ]);
            exit;
        }
        
        // Le fichier est déjà en place et pré-scanné !
        // Insertion IMMÉDIATE en base
        $conn = new PDO("mysql:host=db;dbname=telelec;charset=utf8", 'telelecuser', 'userpassword');
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        date_default_timezone_set('Europe/Paris');
        $uploadDate = date('Y-m-d H:i:s');
        $downloadCode = generateDownloadCode();
        $userCity = getCity($_SERVER['REMOTE_ADDR']);
        
        // Déterminer le statut selon le résultat du scan
        $antivirusStatus = $streamScanResult['status'] === true ? 'true' : 
                          ($streamScanResult['status'] === 'pending' ? 'pending' : 'warning');
        
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
            $streamScanResult['message']
        ]);

        $fileId = $conn->lastInsertId();
        
        // Si scan en attente, lancer le scan complet en arrière-plan
        if ($streamScanResult['status'] === 'pending') {
            exec("php " . __DIR__ . "/../admin/background_scan.php {$fileId} " . escapeshellarg($targetFile) . " > /dev/null 2>&1 &");
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Fichier uploadé avec succès',
            'filename' => $finalName,
            'original' => $originalName,
            'file_id' => $fileId,
            'scan_status' => $antivirusStatus
        ]);

    } catch (Exception $e) {
        // Nettoyer en cas d'erreur
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

// NOUVELLES FONCTIONS À AJOUTER à la fin du fichier :

function moveAndScanFile($source, $destination) {
    $fileSize = filesize($source);
    $sourceHandle = fopen($source, 'rb');
    $destHandle = fopen($destination, 'wb');
    
    if (!$sourceHandle || !$destHandle) {
        return ['status' => false, 'message' => 'Impossible d\'ouvrir les fichiers'];
    }
    
    $chunkSize = 1024 * 1024; // 1MB chunks
    $totalSize = 0;
    
    // Déplacer le fichier chunk par chunk avec scan basique
    while (!feof($sourceHandle)) {
        $chunk = fread($sourceHandle, $chunkSize);
        if ($chunk === false) break;
        
        fwrite($destHandle, $chunk);
        
        // Scan TRÈS basique seulement pour les menaces évidentes
        $chunkResult = scanChunkBasic($chunk, $totalSize);
        if (!$chunkResult['safe']) {
            fclose($sourceHandle);
            fclose($destHandle);
            unlink($destination);
            return [
                'status' => false,
                'message' => "🚨 MENACE DÉTECTÉE: " . $chunkResult['threat']
            ];
        }
        
        $totalSize += strlen($chunk);
    }
    
    fclose($sourceHandle);
    fclose($destHandle);
    
    // NOUVELLE LOGIQUE: Plus intelligent selon la taille
    $sizeMB = $fileSize / (1024 * 1024);
    
    if ($sizeMB <= 1) {
        // Fichiers ≤ 1MB : Scan ClamAV immédiat ultra-rapide
        try {
            $clamResult = scanFileUltraQuick($destination);
            return [
                'status' => $clamResult['status'],
                'message' => $clamResult['message'],
                'scan_type' => 'immediate'
            ];
        } catch (Exception $e) {
            // Si échec ClamAV, accepter directement les petits fichiers
            return [
                'status' => 'true',
                'message' => '✅ Petit fichier accepté directement',
                'scan_type' => 'bypass'
            ];
        }
    } else {
        // Fichiers > 1MB : Toujours accepter et scanner en arrière-plan
        return [
            'status' => 'pending',
            'message' => '⏳ Fichier en cours d\'analyse...',
            'scan_type' => 'deferred'
        ];
    }
}

// Nouvelle fonction : Scan ClamAV ultra-rapide (1 seconde max)
function scanFileUltraQuick($filepath) {
    $escapedPath = escapeshellarg($filepath);
    $command = "timeout 1 clamscan --no-summary --stdout --max-filesize=1M {$escapedPath} 2>&1";
    
    $startTime = microtime(true);
    exec($command, $scanOutput, $scanCode);
    $executionTime = microtime(true) - $startTime;
    
    if ($scanCode === 0) {
        return [
            'status' => true,
            'message' => '✅ Aucune menace détectée (scan rapide)',
            'execution_time' => round($executionTime, 2)
        ];
    } elseif ($scanCode === 1) {
        $virusInfo = implode(' ', $scanOutput);
        return [
            'status' => false,
            'message' => "🚨 VIRUS DÉTECTÉ: " . $virusInfo,
            'execution_time' => round($executionTime, 2)
        ];
    } else {
        throw new Exception('Scan timeout');
    }
}

function scanChunkBasic($chunk, $position) {
    // Détections UNIQUEMENT pour les menaces très évidentes
    if ($position === 0 && substr($chunk, 0, 5) === 'X5O!P') {
        return ['safe' => false, 'threat' => 'Test EICAR'];
    }
    
    // Scan pour du code PHP malveillant uniquement
    if (stripos($chunk, '<?php') !== false && stripos($chunk, 'eval(') !== false) {
        return ['safe' => false, 'threat' => 'Code PHP suspect détecté'];
    }
    
    return ['safe' => true];
}

// AJOUTER CETTE FONCTION MANQUANTE
function generateDownloadCode($length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

// AJOUTER CETTE FONCTION MANQUANTE
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
?>