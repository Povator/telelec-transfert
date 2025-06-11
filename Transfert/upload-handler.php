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
    $sourceHandle = fopen($source, 'rb');
    $destHandle = fopen($destination, 'wb');
    
    if (!$sourceHandle || !$destHandle) {
        return ['status' => false, 'message' => 'Impossible d\'ouvrir les fichiers'];
    }
    
    $chunkSize = 1024 * 1024; // 1MB chunks
    $totalSize = 0;
    $scanBuffer = '';
    
    while (!feof($sourceHandle)) {
        $chunk = fread($sourceHandle, $chunkSize);
        if ($chunk === false) break;
        
        // Écrire immédiatement dans le fichier final
        fwrite($destHandle, $chunk);
        
        // Scan rapide du chunk
        $chunkResult = scanChunkBasic($chunk, $totalSize);
        if (!$chunkResult['safe']) {
            fclose($sourceHandle);
            fclose($destHandle);
            unlink($destination); // Supprimer le fichier infecté
            return [
                'status' => false,
                'message' => "🚨 MENACE DÉTECTÉE: " . $chunkResult['threat']
            ];
        }
        
        $totalSize += strlen($chunk);
    }
    
    fclose($sourceHandle);
    fclose($destHandle);
    
    // Fichier transféré avec succès, déterminer si scan complet nécessaire
    if ($totalSize < 10 * 1024 * 1024) { // < 10MB
        // Scan ClamAV immédiat
        try {
            $clamResult = scanFile($destination);
            return [
                'status' => $clamResult['status'],
                'message' => $clamResult['message']
            ];
        } catch (Exception $e) {
            return [
                'status' => 'warning',
                'message' => '✅ Pré-scan OK, ClamAV indisponible'
            ];
        }
    } else {
        // Scan en arrière-plan pour gros fichiers
        return [
            'status' => 'pending',
            'message' => '✅ Fichier accepté, scan complet en cours...'
        ];
    }
}

function scanChunkBasic($chunk, $position) {
    // Détections rapides sur le chunk
    $threats = [
        'EICAR-STANDARD-ANTIVIRUS-TEST' => 'Test EICAR',
        'MZ' => 'Exécutable Windows (PE)',
        "\x7fELF" => 'Exécutable Linux (ELF)',
        'eval(' => 'Code PHP suspect',
        'base64_decode(' => 'Décodage Base64 suspect',
        'system(' => 'Commande système',
        'exec(' => 'Exécution de commande',
        'shell_exec(' => 'Exécution shell',
        '<script' => 'Script JavaScript suspect'
    ];
    
    foreach ($threats as $pattern => $description) {
        if (stripos($chunk, $pattern) !== false) {
            return ['safe' => false, 'threat' => $description];
        }
    }
    
    return ['safe' => true];
}
?>